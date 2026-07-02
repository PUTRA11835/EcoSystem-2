<div class="space-y-6">
    <!-- QUALIFICATION INFORMATION SECTION (Form untuk Create & Update) -->
    <div>
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Qualification Information</h3>
            <div class="flex gap-2">
                <button type="button" onclick="clearQualificationForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    New
                </button>
                <button type="button" onclick="saveQualification()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span id="saveQualificationButtonText">Save</span>
                </button>
            </div>
        </div>
        
        <input type="hidden" id="editQualificationId">
        
        <!-- Qualification Details Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Qualification Details</h5>
            <div class="grid grid-cols-6 gap-4 form-grid">
                <!-- Qualification Type -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Type <span class="text-red-600">*</span></label>
                    <div class="custom-dd relative" data-onchange="toggleQualificationFields">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Type</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="qualificationType" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Type</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Education">Education</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Certification">Certification</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Language">Language</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Skill">Skill</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Training">Training</button>
                        </div>
                    </div>
                </div>

                <!-- Module/Course (for Education, Certification, Training) -->
                <div class="col-span-2" id="moduleField">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Module/Course</label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Module</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="qualificationModuleId" value="">
                        <div id="moduleDropdownPanel" class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-50 transition-colors" data-value="">Select Module</button>
                        </div>
                    </div>
                </div>

                <!-- Language (for Language type) -->
                <div class="col-span-2 hidden" id="languageField">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Language</label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Language</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="qualificationLanguage" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Language</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="English">English</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Indonesian">Indonesian</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mandarin">Mandarin</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Japanese">Japanese</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Korean">Korean</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Arabic">Arabic</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="French">French</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="German">German</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Spanish">Spanish</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Other">Other</button>
                        </div>
                    </div>
                </div>

                <!-- Qualification Level -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Level</label>
                    <input type="text" id="qualificationLevel" placeholder="Advanced" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- First Year -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Issue Year</label>
                    <input type="text" id="qualificationFirstYear" placeholder="2020" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Checkboxes -->
                <div class="col-span-1 flex items-end gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="qualificationCertified" class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-xs font-semibold text-gray-700">Certified</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-6 gap-4 form-grid mt-4">
                <!-- DPM -->
                <div class="col-span-1 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="qualificationDpm" class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-xs font-semibold text-gray-700">DPM</span>
                    </label>
                </div>

                <!-- DSM -->
                <div class="col-span-1 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="qualificationDsm" class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-xs font-semibold text-gray-700">DSM</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Attachments & Validity Section -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h5 class="text-sm font-bold text-gray-900 mb-3"> Attachments & Validity</h5>
            <div class="grid grid-cols-6 gap-4 form-grid">
                <!-- Verify Link -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                    <input type="url" id="qualificationVerifyLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Drive Link -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Drive Link</label>
                    <input type="url" id="qualificationDriveLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Valid From -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                    <input type="date" id="qualificationValidFrom" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Valid To -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                    <input type="date" id="qualificationValidTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>
    </div>

    <!-- QUALIFICATION DETAILS SECTION (Table) -->
    <div>
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
            <h3 class="text-base font-semibold text-gray-900">Qualification Details</h3>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="qualificationSearch" placeholder="Search" class="w-full sm:w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedQualification()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="deleteSelectedQualification()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                    Delete
                </button>

                <!-- Settings/Options button -->
                <button title="Settings" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </button>

                <!-- Export/Download button -->
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
                            <input type="radio" name="selectedQualification" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Module/Language</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Level</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Year</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Validity</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="qualificationTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here -->
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No qualifications found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new qualification</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteQualificationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Qualification</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this qualification?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteQualificationInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteQualification()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteQualification()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let qualificationsData = [];
    let selectedQualificationId = null;
    let deleteQualificationId = null;

    async function loadModuleDropdown() {
        try {
            const res = await fetch('/api/modules?is_active=true', { credentials: 'same-origin' });
            const data = await res.json();
            const panel = document.getElementById('moduleDropdownPanel');
            if (!data.success) return;
            panel.innerHTML = `<button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-400 hover:bg-gray-50 transition-colors" data-value="">Select Module</button>`;
            data.data.forEach(m => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors';
                btn.dataset.value = m.id;
                btn.textContent = m.name;
                panel.appendChild(btn);
            });
        } catch (e) {
            console.error('Failed to load modules', e);
        }
    }

    function setModuleDropdownValue(moduleId, moduleName) {
        document.getElementById('qualificationModuleId').value = moduleId || '';
        const btn = document.querySelector('#moduleField .custom-dd-btn .custom-dd-label');
        if (btn) btn.textContent = moduleName || 'Select Module';
        if (!moduleName) btn.classList.add('text-gray-500');
        else btn.classList.remove('text-gray-500');
    }

    /**
     * Toggle fields based on qualification type
     */
    function toggleQualificationFields() {
        const type = document.getElementById('qualificationType').value;
        const moduleField = document.getElementById('moduleField');
        const languageField = document.getElementById('languageField');
        
        // Hide all conditional fields
        moduleField.classList.add('hidden');
        languageField.classList.add('hidden');
        
        // Show relevant fields
        if (type === 'Education' || type === 'Certification' || type === 'Training') {
            moduleField.classList.remove('hidden');
        } else if (type === 'Language') {
            languageField.classList.remove('hidden');
        }
    }

    /**
     * Load all qualifications for this employee
     */
    async function loadQualifications() {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/qualification`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                qualificationsData = data.data;
                renderQualificationTable(data.data);
            } else {
                qualificationsData = [];
                renderEmptyTable();
            }
        } catch (error) {
            console.error(' Error loading qualifications:', error);
            qualificationsData = [];
            renderEmptyTable();
        }
    }

    /**
     * Render qualification table with data
     */
    function renderQualificationTable(qualifications) {
        const tbody = document.getElementById('qualificationTableBody');
        
        tbody.innerHTML = qualifications.map(qual => {
            const moduleOrLanguage = qual.module || qual.language || '-';
            // qual.module sudah berupa nama (dari relasi via getFullInformation)
            const validity = formatValidity(qual.valid_from, qual.valid_to);
            const statusBadge = getStatusBadge(qual);

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectQualificationRow(${qual.qualification_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedQualification" value="${qual.qualification_id}" 
                            onclick="selectQualification(${qual.qualification_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${qual.qualification_type || '-'}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${moduleOrLanguage}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${qual.qualification_level || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${qual.first_year || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validity}</td>
                    <td class="px-4 py-3 text-sm">${statusBadge}</td>
                    <td class="px-4 py-3">
                        <button onclick="loadQualificationToForm(${qual.qualification_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
        const tbody = document.getElementById('qualificationTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No qualifications found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new qualification</small>
                </td>
            </tr>
        `;
    }

    /**
     * Format validity period
     */
    function formatValidity(validFrom, validTo) {
        if (!validFrom && !validTo) return '-';
        const from = validFrom ? new Date(validFrom).toLocaleDateString('id-ID', { year: 'numeric', month: 'short' }) : '?';
        const to = validTo ? new Date(validTo).toLocaleDateString('id-ID', { year: 'numeric', month: 'short' }) : 'Present';
        return `${from} - ${to}`;
    }

    /**
     * Get status badge
     */
    function getStatusBadge(qual) {
        if (!qual.valid_to) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">No Expiry</span>';
        }
        
        const today = new Date();
        const validTo = new Date(qual.valid_to);
        
        if (validTo < today) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Expired</span>';
        } else if ((validTo - today) / (1000 * 60 * 60 * 24) <= 30) {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Expiring Soon</span>';
        } else {
            return '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Valid</span>';
        }
    }

    /**
     * Select qualification from radio button
     */
    function selectQualification(qualificationId) {
        selectedQualificationId = qualificationId;
        loadQualificationToForm(qualificationId);
    }

    /**
     * Select qualification row (when clicking on row)
     */
    function selectQualificationRow(qualificationId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedQualification"][value="${qualificationId}"]`);
        if (radio) {
            radio.checked = true;
            selectQualification(qualificationId);
        }
    }

    /**
     * Load qualification data to form fields
     */
    async function loadQualificationToForm(qualificationId) {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/qualification/${qualificationId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const qual = data.data;
                
                // Set hidden ID untuk update
                document.getElementById('editQualificationId').value = qual.qualification_id;
                
                // Set form values
                setCustomDropdownValue('qualificationType', qual.qualification_type || '');
                toggleQualificationFields(); // Toggle fields based on type

                setModuleDropdownValue(qual.module_id || '', qual.module || '');
                setCustomDropdownValue('qualificationLanguage', qual.language || '');
                document.getElementById('qualificationLevel').value = qual.qualification_level || '';
                document.getElementById('qualificationFirstYear').value = qual.first_year || '';
                document.getElementById('qualificationCertified').checked = qual.certified || false;
                document.getElementById('qualificationDpm').checked = qual.dpm || false;
                document.getElementById('qualificationDsm').checked = qual.dsm || false;
                document.getElementById('qualificationVerifyLink').value = qual.verify_link || '';
                document.getElementById('qualificationDriveLink').value = qual.drive_link || '';
                document.getElementById('qualificationValidFrom').value = qual.valid_from || '';
                document.getElementById('qualificationValidTo').value = qual.valid_to || '';
                
                // Update button text
                document.getElementById('saveQualificationButtonText').textContent = 'Update';
                
            }
        } catch (error) {
            console.error(' Error loading qualification:', error);
        }
    }

    /**
     * Clear form (for create new)
     */
    function clearQualificationForm() {
        document.getElementById('editQualificationId').value = '';
        setCustomDropdownValue('qualificationType', '');
        setModuleDropdownValue('', '');
        setCustomDropdownValue('qualificationLanguage', '');
        document.getElementById('qualificationLevel').value = '';
        document.getElementById('qualificationFirstYear').value = '';
        document.getElementById('qualificationCertified').checked = false;
        document.getElementById('qualificationDpm').checked = false;
        document.getElementById('qualificationDsm').checked = false;
        document.getElementById('qualificationVerifyLink').value = '';
        document.getElementById('qualificationDriveLink').value = '';
        document.getElementById('qualificationValidFrom').value = '';
        document.getElementById('qualificationValidTo').value = '';
        
        toggleQualificationFields(); // Reset field visibility
        
        // Uncheck radio
        const radios = document.querySelectorAll('input[name="selectedQualification"]');
        radios.forEach(radio => radio.checked = false);
        
        selectedQualificationId = null;
        
        // Update button text
        document.getElementById('saveQualificationButtonText').textContent = 'Save';
        
        showNotification('Form cleared. Ready to create new qualification.', 'info');
    }

    /**
     * Save qualification (create or update)
     */
    async function saveQualification() {
        // Validate required fields
        const qualificationType = document.getElementById('qualificationType').value;
        
        if (!qualificationType) {
            showNotification('Qualification type is required', 'error');
            return;
        }

        const qualificationId = document.getElementById('editQualificationId').value;
        const isUpdate = qualificationId !== '';

        const qualificationData = {
            qualification_type: qualificationType,
            module_id: document.getElementById('qualificationModuleId').value ? parseInt(document.getElementById('qualificationModuleId').value) : null,
            language: document.getElementById('qualificationLanguage').value || null,
            qualification_level: document.getElementById('qualificationLevel').value || null,
            first_year: document.getElementById('qualificationFirstYear').value || null,
            certified: document.getElementById('qualificationCertified').checked,
            dpm: document.getElementById('qualificationDpm').checked,
            dsm: document.getElementById('qualificationDsm').checked,
            verify_link: document.getElementById('qualificationVerifyLink').value || null,
            drive_link: document.getElementById('qualificationDriveLink').value || null,
            valid_from: document.getElementById('qualificationValidFrom').value || null,
            valid_to: document.getElementById('qualificationValidTo').value || null
        };

        try {
            const url = isUpdate 
                ? `/api/employees/${employeeId}/qualification/${qualificationId}`
                : `/api/employees/${employeeId}/qualification`;
            
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
                body: JSON.stringify(qualificationData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification(
                    isUpdate ? 'Qualification updated successfully!' : 'Qualification created successfully!', 
                    'success'
                );
                loadQualifications();
                
                if (!isUpdate) {
                    clearQualificationForm();
                } else {
                    // Reload the same record
                    loadQualificationToForm(qualificationId);
                }
            } else {
                showNotification('Failed to save qualification: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error saving qualification:', error);
            showNotification('An error occurred while saving qualification', 'error');
        }
    }

    /**
     * Copy selected qualification
     */
    function copySelectedQualification() {
        if (!selectedQualificationId) {
            showNotification('Please select a qualification first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected qualification
     */
    function deleteSelectedQualification() {
        if (!selectedQualificationId) {
            showNotification('Please select a qualification first', 'warning');
            return;
        }
        
        deleteQualificationId = selectedQualificationId;
        
        const qual = qualificationsData.find(q => q.qualification_id === selectedQualificationId);
        let qualInfo = 'this qualification';
        
        if (qual) {
            const display = qual.module || qual.language || qual.qualification_type;
            qualInfo = `${qual.qualification_type} - ${display}`;
        }
        
        document.getElementById('deleteQualificationInfo').textContent = qualInfo;
        document.getElementById('confirmDeleteQualificationModal').classList.remove('hidden');
        document.getElementById('confirmDeleteQualificationModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteQualification() {
        document.getElementById('confirmDeleteQualificationModal').classList.add('hidden');
        document.getElementById('confirmDeleteQualificationModal').classList.remove('flex');
        deleteQualificationId = null;
    }

    /**
     * Confirm delete qualification
     */
    async function confirmDeleteQualification() {
        if (!deleteQualificationId) return;

        try {
            
            const response = await fetch(`/api/employees/${employeeId}/qualification/${deleteQualificationId}`, {
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
                showNotification('Qualification deleted successfully!', 'success');
                closeConfirmDeleteQualification();
                selectedQualificationId = null;
                loadQualifications();
                clearQualificationForm();
            } else {
                showNotification('Failed to delete qualification: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error deleting qualification:', error);
            showNotification('An error occurred while deleting qualification', 'error');
        }
    }

    /**
     * Show notification
     */

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadQualifications();
        loadModuleDropdown();
        toggleQualificationFields();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteQualificationModal').classList.contains('hidden')) {
                closeConfirmDeleteQualification();
            }
        }
    });
</script>
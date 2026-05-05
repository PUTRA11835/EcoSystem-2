<div class="space-y-6">
    <!-- EDUCATION INFORMATION SECTION (Form untuk Create & Update) -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Education Information</h3>
            <div class="flex gap-2">
                <button type="button" onclick="clearEducationForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    New
                </button>
                <button type="button" onclick="saveEducation()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span id="saveEducationButtonText">Save</span>
                </button>
            </div>
        </div>
        
        <input type="hidden" id="editEducationId">
        
        <!-- Basic Education Information Section -->
        <div class="mb-6">
            <div class="grid grid-cols-6 gap-4">
                <!-- Education Level -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Education Level <span class="text-red-600">*</span></label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Level</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="educationType" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Level</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="SD">SD - Elementary</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="SMP">SMP - Junior High</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="SMA">SMA - Senior High</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="SMK">SMK - Vocational</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="D1">D1 - Diploma 1</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="D2">D2 - Diploma 2</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="D3">D3 - Diploma 3</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="D4">D4 - Diploma 4</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="S1">S1 - Bachelor</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="S2">S2 - Master</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="S3">S3 - Doctoral</button>
                        </div>
                    </div>
                </div>

                <!-- Institution Name -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Institution Name <span class="text-red-600">*</span></label>
                    <input type="text" id="institutePlace" required placeholder="e.g., University of Indonesia" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Country -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Country</label>
                    <input type="text" id="educationCountry" value="Indonesia" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Degree -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Degree</label>
                    <input type="text" id="degree" placeholder="e.g., Bachelor of Science" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Major/Field of Study -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Major/Field</label>
                    <input type="text" id="branchOfStudy" placeholder="e.g., Computer Science" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>

            <div class="grid grid-cols-6 gap-4 mt-4">
                <!-- Unit/Faculty -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Unit/Faculty</label>
                    <input type="text" id="unit" placeholder="e.g., Faculty of Engineering" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Duration of Course -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Duration</label>
                    <input type="text" id="durationOfCourse" placeholder="e.g., 4 years" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Start Year -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Start Year</label>
                    <input type="number" id="startYear" placeholder="2015" min="1950" max="2100" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Graduation Year -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Graduation Year</label>
                    <input type="number" id="graduationYear" placeholder="2019" min="1950" max="2100" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Final Grade -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Final Grade/GPA</label>
                    <input type="text" id="finalGrade" placeholder="3.75, A, Cum Laude" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>

        <!-- Attachments Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">Attachments</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Attachment Name -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Attachment Name</label>
                    <input type="text" id="attachmentName" placeholder="e.g., Diploma Certificate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Verify Link -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                    <input type="url" id="attachmentVerifyLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Drive Link -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Drive Link</label>
                    <input type="url" id="attachmentDriveLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
                <!-- Valid From -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                    <input type="date" id="educationValidFrom" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Valid To -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                    <input type="date" id="educationValidTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>
    </div>

    <!-- EDUCATION DETAILS SECTION (Table) -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Education Details</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="educationSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedEducation()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="deleteSelectedEducation()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
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
                            <input type="radio" name="selectedEducation" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Institution</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Level</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Degree</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Major</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Period</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Grade</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="educationTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here -->
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No education records found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new education record</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteEducationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Education Record</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this education record?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteEducationInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteEducation()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteEducation()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let educationsData = [];
    let selectedEducationId = null;
    let deleteEducationId = null;

    /**
     * Load all educations for this employee
     */
    async function loadEducations() {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/education`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                educationsData = data.data;
                renderEducationTable(data.data);
            } else {
                educationsData = [];
                renderEmptyTable();
            }
        } catch (error) {
            console.error(' Error loading educations:', error);
            educationsData = [];
            renderEmptyTable();
        }
    }

    /**
     * Render education table with data
     */
    function renderEducationTable(educations) {
        const tbody = document.getElementById('educationTableBody');
        
        tbody.innerHTML = educations.map(education => {
            const period = formatEducationPeriod(education.start_year, education.graduation_year);

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectEducationRow(${education.education_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedEducation" value="${education.education_id}" 
                            onclick="selectEducation(${education.education_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${education.institute_place || '-'}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            ${education.education_type || '-'}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${education.degree || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${education.branch_of_study || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${period}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${education.final_grade || '-'}</td>
                    <td class="px-4 py-3">
                        <button onclick="loadEducationToForm(${education.education_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
        const tbody = document.getElementById('educationTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No education records found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new education record</small>
                </td>
            </tr>
        `;
    }

    /**
     * Format education period
     */
    function formatEducationPeriod(startYear, graduationYear) {
        if (!startYear && !graduationYear) return '-';
        const start = startYear || '?';
        const end = graduationYear || 'Present';
        return `${start} - ${end}`;
    }

    /**
     * Select education from radio button
     */
    function selectEducation(educationId) {
        selectedEducationId = educationId;
        loadEducationToForm(educationId);
    }

    /**
     * Select education row (when clicking on row)
     */
    function selectEducationRow(educationId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedEducation"][value="${educationId}"]`);
        if (radio) {
            radio.checked = true;
            selectEducation(educationId);
        }
    }

    /**
     * Load education data to form fields
     */
    async function loadEducationToForm(educationId) {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/education/${educationId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const education = data.data;
                
                // Set hidden ID untuk update
                document.getElementById('editEducationId').value = education.education_id;
                
                // Set form values
                setCustomDropdownValue('educationType', education.education_type || '');
                document.getElementById('institutePlace').value = education.institute_place || '';
                document.getElementById('educationCountry').value = education.country || '';
                document.getElementById('degree').value = education.degree || '';
                document.getElementById('branchOfStudy').value = education.branch_of_study || '';
                document.getElementById('unit').value = education.unit || '';
                document.getElementById('durationOfCourse').value = education.duration_of_course || '';
                document.getElementById('startYear').value = education.start_year || '';
                document.getElementById('graduationYear').value = education.graduation_year || '';
                document.getElementById('finalGrade').value = education.final_grade || '';
                document.getElementById('attachmentName').value = education.attachment_name || '';
                document.getElementById('attachmentVerifyLink').value = education.attachment_verify_link || '';
                document.getElementById('attachmentDriveLink').value = education.attachment_drive_link || '';
                document.getElementById('educationValidFrom').value = education.valid_from || '';
                document.getElementById('educationValidTo').value = education.valid_to || '';
                
                // Update button text
                document.getElementById('saveEducationButtonText').textContent = 'Update';
                
            }
        } catch (error) {
            console.error(' Error loading education:', error);
        }
    }

    /**
     * Clear form (for create new)
     */
    function clearEducationForm() {
        document.getElementById('editEducationId').value = '';
        setCustomDropdownValue('educationType', '');
        document.getElementById('institutePlace').value = '';
        document.getElementById('educationCountry').value = 'Indonesia';
        document.getElementById('degree').value = '';
        document.getElementById('branchOfStudy').value = '';
        document.getElementById('unit').value = '';
        document.getElementById('durationOfCourse').value = '';
        document.getElementById('startYear').value = '';
        document.getElementById('graduationYear').value = '';
        document.getElementById('finalGrade').value = '';
        document.getElementById('attachmentName').value = '';
        document.getElementById('attachmentVerifyLink').value = '';
        document.getElementById('attachmentDriveLink').value = '';
        document.getElementById('educationValidFrom').value = '';
        document.getElementById('educationValidTo').value = '';
        
        // Uncheck radio
        const radios = document.querySelectorAll('input[name="selectedEducation"]');
        radios.forEach(radio => radio.checked = false);
        
        selectedEducationId = null;
        
        // Update button text
        document.getElementById('saveEducationButtonText').textContent = 'Save';
        
        showNotification('Form cleared. Ready to create new education record.', 'info');
    }

    /**
     * Save education (create or update)
     */
    async function saveEducation() {
        // Validate required fields
        const educationType = document.getElementById('educationType').value;
        const institutePlace = document.getElementById('institutePlace').value;
        
        if (!educationType || !institutePlace) {
            showNotification('Education level and institution name are required', 'error');
            return;
        }

        const educationId = document.getElementById('editEducationId').value;
        const isUpdate = educationId !== '';

        const educationData = {
            education_type: educationType,
            institute_place: institutePlace,
            country: document.getElementById('educationCountry').value || null,
            degree: document.getElementById('degree').value || null,
            branch_of_study: document.getElementById('branchOfStudy').value || null,
            unit: document.getElementById('unit').value || null,
            duration_of_course: document.getElementById('durationOfCourse').value || null,
            start_year: document.getElementById('startYear').value || null,
            graduation_year: document.getElementById('graduationYear').value || null,
            final_grade: document.getElementById('finalGrade').value || null,
            attachment_name: document.getElementById('attachmentName').value || null,
            attachment_verify_link: document.getElementById('attachmentVerifyLink').value || null,
            attachment_drive_link: document.getElementById('attachmentDriveLink').value || null,
            valid_from: document.getElementById('educationValidFrom').value || null,
            valid_to: document.getElementById('educationValidTo').value || null
        };

        try {
            const url = isUpdate 
                ? `/api/employees/${employeeId}/education/${educationId}`
                : `/api/employees/${employeeId}/education`;
            
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
                body: JSON.stringify(educationData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification(
                    isUpdate ? 'Education updated successfully!' : 'Education created successfully!', 
                    'success'
                );
                loadEducations();
                
                if (!isUpdate) {
                    clearEducationForm();
                } else {
                    // Reload the same record
                    loadEducationToForm(educationId);
                }
            } else {
                showNotification('Failed to save education: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error saving education:', error);
            showNotification('An error occurred while saving education', 'error');
        }
    }

    /**
     * Copy selected education
     */
    function copySelectedEducation() {
        if (!selectedEducationId) {
            showNotification('Please select an education record first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected education
     */
    function deleteSelectedEducation() {
        if (!selectedEducationId) {
            showNotification('Please select an education record first', 'warning');
            return;
        }
        
        deleteEducationId = selectedEducationId;
        
        const education = educationsData.find(e => e.education_id === selectedEducationId);
        let educationInfo = 'this education record';
        
        if (education) {
            educationInfo = `${education.education_type || ''} - ${education.institute_place || ''}`;
        }
        
        document.getElementById('deleteEducationInfo').textContent = educationInfo;
        document.getElementById('confirmDeleteEducationModal').classList.remove('hidden');
        document.getElementById('confirmDeleteEducationModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteEducation() {
        document.getElementById('confirmDeleteEducationModal').classList.add('hidden');
        document.getElementById('confirmDeleteEducationModal').classList.remove('flex');
        deleteEducationId = null;
    }

    /**
     * Confirm delete education
     */
    async function confirmDeleteEducation() {
        if (!deleteEducationId) return;

        try {
            
            const response = await fetch(`/api/employees/${employeeId}/education/${deleteEducationId}`, {
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
                showNotification('Education deleted successfully!', 'success');
                closeConfirmDeleteEducation();
                selectedEducationId = null;
                loadEducations();
                clearEducationForm();
            } else {
                showNotification('Failed to delete education: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error deleting education:', error);
            showNotification('An error occurred while deleting education', 'error');
        }
    }

    /**
     * Show notification
     */

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadEducations();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteEducationModal').classList.contains('hidden')) {
                closeConfirmDeleteEducation();
            }
        }
    });
</script>
<div class="space-y-6 {{ (isset($isReadonly) && $isReadonly) ? 'profile-readonly' : '' }}">
    <!-- FAMILY INFORMATION SECTION (Form untuk Create & Update) -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <h3 class="text-base font-semibold text-gray-900">Family Information</h3>
                @if(isset($isReadonly) && $isReadonly)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium"><i class="fas fa-lock text-[10px]"></i> View Only</span>
                @endif
            </div>
            <div class="flex gap-2 js-section-action">
                <button type="button" onclick="clearFamilyForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    New
                </button>
                <button type="button" onclick="saveFamily()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                    </svg>
                    <span id="saveFamilyButtonText">Save</span>
                </button>
            </div>
        </div>
        
        <input type="hidden" id="editFamilyId">
        
        <!-- Personal Information Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Personal Information</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Relation -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Relation <span class="text-red-600">*</span></label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Relation</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="familyRelation" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Relation</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="spouse">Spouse</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="child">Child</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="parent">Parent</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="father">Father</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="mother">Mother</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="sibling">Sibling</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="brother">Brother</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="sister">Sister</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="other">Other</button>
                        </div>
                    </div>
                </div>

                <!-- Title -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Title</label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Title</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="familyTitle" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Title</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mr.">Mr.</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mrs.">Mrs.</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Ms.">Ms.</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Dr.">Dr.</button>
                        </div>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full Name <span class="text-red-600">*</span></label>
                    <input type="text" id="familyName" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Gender -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Gender</label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Gender</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="familyGender" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Gender</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Male">Male</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Female">Female</button>
                        </div>
                    </div>
                </div>

                <!-- Religion -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Religion</label>
                    <div class="custom-dd relative">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-500">Select Religion</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="familyReligion" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Religion</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Islam">Islam</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Christianity">Christianity</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Catholicism">Catholicism</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Hinduism">Hinduism</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Buddhism">Buddhism</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Confucianism">Confucianism</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Other">Other</button>
                        </div>
                    </div>
                </div>

                <!-- Country -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Country</label>
                    <input type="text" id="familyCountry" value="Indonesia" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>
            </div>
        </div>

        <!-- Birth Information Section -->
        <div class="mb-6">
            <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200"> Birth Information</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Birth Place -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Birth Place</label>
                    <input type="text" id="familyBirthPlace" placeholder="e.g., Jakarta, Surabaya" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Birth Date -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Birth Date</label>
                    <input type="date" id="familyBirthDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Occupation -->
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Occupation</label>
                    <input type="text" id="familyOccupation" placeholder="e.g., Teacher, Engineer" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Is Alive -->
                <div class="col-span-1 flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="familyIsAlive" checked class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-xs font-semibold text-gray-700">Is Alive</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Validity & Verify Link Section -->
        <div class="bg-gray-50 rounded-lg p-4">
            <h5 class="text-sm font-bold text-gray-900 mb-3">Validity & Documents</h5>
            <div class="grid grid-cols-6 gap-4">
                <!-- Valid From -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                    <input type="date" id="familyValidFrom" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Valid To -->
                <div class="col-span-1">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                    <input type="date" id="familyValidTo" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                </div>

                <!-- Verify Link -->
                <div class="col-span-4">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Verify Link</label>
                    <input type="url" id="familyVerifyLink" placeholder="https://" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                    <small class="text-xs text-gray-500 mt-1">Link to verification document</small>
                </div>
            </div>
        </div>
    </div>

    <!-- FAMILY DETAILS SECTION (Table) -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Family Details</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="familySearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedFamily()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>

                <button onclick="deleteSelectedFamily()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
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
                            <input type="radio" name="selectedFamily" class="w-4 h-4 text-red-800 focus:ring-red-800" disabled>
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Relation</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Birth Date</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Gender</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Occupation</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="familyTableBody" class="bg-white divide-y divide-gray-100">
                    <!-- Dynamic rows will be inserted here -->
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No family members found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new family member</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="confirmDeleteFamilyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Family Member</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this family member?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteFamilyInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteFamily()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteFamily()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    let familiesData = [];
    let selectedFamilyId = null;
    let deleteFamilyId = null;

    /**
     * Load all families for this employee
     */
    async function loadFamilies() {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/family`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                familiesData = data.data;
                renderFamilyTable(data.data);
            } else {
                familiesData = [];
                renderEmptyTable();
            }
        } catch (error) {
            console.error(' Error loading families:', error);
            familiesData = [];
            renderEmptyTable();
        }
    }

    /**
     * Render family table with data
     */
    function renderFamilyTable(families) {
        const tbody = document.getElementById('familyTableBody');
        
        tbody.innerHTML = families.map(family => {
            const fullName = family.title ? `${family.title} ${family.name}` : family.name;
            const relation = getRelationLabel(family.relation);
            const birthDate = family.birth_date ? new Date(family.birth_date).toLocaleDateString('en-GB') : '';
            
            const statusBadge = family.is_alive 
                ? '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Alive</span>'
                : '<span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Deceased</span>';

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="selectFamilyRow(${family.family_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedFamily" value="${family.family_id}" 
                            onclick="selectFamily(${family.family_id})" 
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${fullName || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${relation}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${birthDate}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${family.gender || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${family.occupation || '-'}</td>
                    <td class="px-4 py-3 text-sm">${statusBadge}</td>
                    <td class="px-4 py-3">
                        <button onclick="loadFamilyToForm(${family.family_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
        const tbody = document.getElementById('familyTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No family members found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new family member</small>
                </td>
            </tr>
        `;
    }

    /**
     * Get human readable label for relation
     */
    function getRelationLabel(relation) {
        const relations = {
            'spouse': 'Spouse',
            'child': 'Child',
            'parent': 'Parent',
            'father': 'Father',
            'mother': 'Mother',
            'sibling': 'Sibling',
            'brother': 'Brother',
            'sister': 'Sister',
            'other': 'Other'
        };
        return relations[relation] || relation;
    }

    /**
     * Select family from radio button
     */
    function selectFamily(familyId) {
        selectedFamilyId = familyId;
        loadFamilyToForm(familyId);
    }

    /**
     * Select family row (when clicking on row)
     */
    function selectFamilyRow(familyId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedFamily"][value="${familyId}"]`);
        if (radio) {
            radio.checked = true;
            selectFamily(familyId);
        }
    }

    /**
     * Load family data to form fields
     */
    async function loadFamilyToForm(familyId) {
        try {
            
            const response = await fetch(`/api/employees/${employeeId}/family/${familyId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const family = data.data;
                
                // Set hidden ID untuk update
                document.getElementById('editFamilyId').value = family.family_id;
                
                // Set form values
                setCustomDropdownValue('familyRelation', family.relation || '');
                setCustomDropdownValue('familyTitle', family.title || '');
                document.getElementById('familyName').value = family.name || '';
                setCustomDropdownValue('familyGender', family.gender || '');
                setCustomDropdownValue('familyReligion', family.religion || '');
                document.getElementById('familyCountry').value = family.country || '';
                document.getElementById('familyBirthPlace').value = family.birth_place || '';
                document.getElementById('familyBirthDate').value = family.birth_date || '';
                document.getElementById('familyOccupation').value = family.occupation || '';
                document.getElementById('familyIsAlive').checked = family.is_alive !== undefined ? family.is_alive : true;
                document.getElementById('familyValidFrom').value = family.valid_from || '';
                document.getElementById('familyValidTo').value = family.valid_to || '';
                document.getElementById('familyVerifyLink').value = family.verify_link || '';
                
                // Update button text
                document.getElementById('saveFamilyButtonText').textContent = 'Update';
                
            }
        } catch (error) {
            console.error(' Error loading family:', error);
        }
    }

    /**
     * Clear form (for create new)
     */
    function clearFamilyForm() {
        document.getElementById('editFamilyId').value = '';
        setCustomDropdownValue('familyRelation', '');
        setCustomDropdownValue('familyTitle', '');
        document.getElementById('familyName').value = '';
        setCustomDropdownValue('familyGender', '');
        setCustomDropdownValue('familyReligion', '');
        document.getElementById('familyCountry').value = 'Indonesia';
        document.getElementById('familyBirthPlace').value = '';
        document.getElementById('familyBirthDate').value = '';
        document.getElementById('familyOccupation').value = '';
        document.getElementById('familyIsAlive').checked = true;
        document.getElementById('familyValidFrom').value = '';
        document.getElementById('familyValidTo').value = '';
        document.getElementById('familyVerifyLink').value = '';
        
        // Uncheck radio
        const radios = document.querySelectorAll('input[name="selectedFamily"]');
        radios.forEach(radio => radio.checked = false);
        
        selectedFamilyId = null;
        
        // Update button text
        document.getElementById('saveFamilyButtonText').textContent = 'Save';
        
        showNotification('Form cleared. Ready to create new family member.', 'info');
    }

    /**
     * Save family (create or update)
     */
    async function saveFamily() {
        // Validate required fields
        const relation = document.getElementById('familyRelation').value;
        const name = document.getElementById('familyName').value;
        
        if (!relation || !name) {
            showNotification('Relation and name are required', 'error');
            return;
        }

        const familyId = document.getElementById('editFamilyId').value;
        const isUpdate = familyId !== '';

        const familyData = {
            relation: relation,
            title: document.getElementById('familyTitle').value || null,
            name: name,
            gender: document.getElementById('familyGender').value || null,
            religion: document.getElementById('familyReligion').value || null,
            country: document.getElementById('familyCountry').value || null,
            birth_place: document.getElementById('familyBirthPlace').value || null,
            birth_date: document.getElementById('familyBirthDate').value || null,
            occupation: document.getElementById('familyOccupation').value || null,
            is_alive: document.getElementById('familyIsAlive').checked,
            valid_from: document.getElementById('familyValidFrom').value || null,
            valid_to: document.getElementById('familyValidTo').value || null,
            verify_link: document.getElementById('familyVerifyLink').value || null
        };

        try {
            const url = isUpdate 
                ? `/api/employees/${employeeId}/family/${familyId}`
                : `/api/employees/${employeeId}/family`;
            
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
                body: JSON.stringify(familyData)
            });

            const data = await response.json();
            
            if (data.success) {
                showNotification(
                    isUpdate ? 'Family member updated successfully!' : 'Family member created successfully!', 
                    'success'
                );
                loadFamilies();
                
                if (!isUpdate) {
                    clearFamilyForm();
                } else {
                    // Reload the same record
                    loadFamilyToForm(familyId);
                }
            } else {
                showNotification('Failed to save family member: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error saving family:', error);
            showNotification('An error occurred while saving family member', 'error');
        }
    }

    /**
     * Copy selected family
     */
    function copySelectedFamily() {
        if (!selectedFamilyId) {
            showNotification('Please select a family member first', 'warning');
            return;
        }
        showNotification('Copy feature coming soon', 'info');
    }

    /**
     * Delete selected family
     */
    function deleteSelectedFamily() {
        if (!selectedFamilyId) {
            showNotification('Please select a family member first', 'warning');
            return;
        }
        
        deleteFamilyId = selectedFamilyId;
        
        const family = familiesData.find(f => f.family_id === selectedFamilyId);
        let familyInfo = 'this family member';
        
        if (family) {
            const fullName = family.title ? `${family.title} ${family.name}` : family.name;
            familyInfo = fullName;
        }
        
        document.getElementById('deleteFamilyInfo').textContent = familyInfo;
        document.getElementById('confirmDeleteFamilyModal').classList.remove('hidden');
        document.getElementById('confirmDeleteFamilyModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteFamily() {
        document.getElementById('confirmDeleteFamilyModal').classList.add('hidden');
        document.getElementById('confirmDeleteFamilyModal').classList.remove('flex');
        deleteFamilyId = null;
    }

    /**
     * Confirm delete family
     */
    async function confirmDeleteFamily() {
        if (!deleteFamilyId) return;

        try {
            
            const response = await fetch(`/api/employees/${employeeId}/family/${deleteFamilyId}`, {
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
                showNotification('Family member deleted successfully!', 'success');
                closeConfirmDeleteFamily();
                selectedFamilyId = null;
                loadFamilies();
                clearFamilyForm();
            } else {
                showNotification('Failed to delete family member: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error(' Error deleting family:', error);
            showNotification('An error occurred while deleting family member', 'error');
        }
    }

    /**
     * Show notification
     */

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadFamilies();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteFamilyModal').classList.contains('hidden')) {
                closeConfirmDeleteFamily();
            }
        }
    });
</script>
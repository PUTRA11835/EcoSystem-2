<div class="space-y-6">
    <!-- FORM HEADER -->
    <div class="flex justify-between items-center pb-2 border-b border-gray-200">
        <h3 class="text-base font-semibold text-gray-900">Contact Information</h3>
        <div class="flex gap-2">
            <input type="hidden" id="editContactId">
            <button type="button" onclick="clearContactForm()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-500 text-white text-xs font-semibold rounded-lg hover:bg-gray-600 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                New
            </button>
            <button type="button" onclick="saveContactInline()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                </svg>
                <span id="saveContactButtonText">Save</span>
            </button>
        </div>
    </div>

    <!-- General Data -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">General Data</h3>
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Title</label>
                <input type="text" id="contactTitle"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full Name <span class="text-red-600">*</span></label>
                <input type="text" id="contactName"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Nick Name</label>
                <input type="text" id="contactNickName"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Position</label>
                <input type="text" id="contactPosition"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Department</label>
                <input type="text" id="contactDepartment"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Language</label>
                <input type="text" id="contactLanguage"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Entry Date</label>
                <input type="date" id="contactEntryDate"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid From</label>
                <input type="date" id="contactValidFrom"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valid To</label>
                <input type="date" id="contactValidTo"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>
    </div>

    <!-- Communication -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Communication</h3>
        <div class="grid grid-cols-6 gap-4">
            <!-- Cell Phone -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cell Phone Country</label>
                <input type="text" id="contactCellPhoneCountry" placeholder="+62" maxlength="20"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cell Phone</label>
                <input type="text" id="contactCellPhone" maxlength="20"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <!-- Telephone -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Telephone Country</label>
                <input type="text" id="contactTelCountry" placeholder="+62" maxlength="20"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Telephone</label>
                <input type="text" id="contactTelephone"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Telephone Extension</label>
                <input type="text" id="contactTelExt"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <!-- Fax -->
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Fax Country</label>
                <input type="text" id="contactFaxCountry" placeholder="+62" maxlength="20"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Fax</label>
                <input type="text" id="contactFax"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Fax Extension</label>
                <input type="text" id="contactFaxExt"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <!-- Email & Web -->
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email Personal</label>
                <input type="text" id="contactEmailPersonal"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email Work</label>
                <input type="text" id="contactEmailWork"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Website</label>
                <input type="text" id="contactWebsite"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Preferred Communication</label>
                <input type="text" id="contactPreferredComm"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>
    </div>

    <!-- Contact Details Table -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-base font-semibold text-gray-900">Contact</h3>
            <div class="flex items-center gap-3">
                <!-- Search -->
                <div class="relative">
                    <input type="text" id="contactSearch" placeholder="Search" class="w-64 px-3 py-2 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                </div>

                <!-- Action Buttons -->
                <button onclick="copySelectedContact()" title="Copy" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Copy
                </button>





                <button onclick="openGrantLoginModal()" id="btnGrantLogin" title="Grant Jarvies Access" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white text-xs font-semibold rounded-lg hover:bg-green-700 transition-all hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z" />
                    </svg>
                    Grant Access
                </button>

                <button onclick="revokeLoginAccess()" id="btnRevokeLogin" title="Revoke Jarvies Access" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-orange-500 text-orange-600 text-xs font-semibold rounded-lg hover:bg-orange-50 transition-all hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Revoke Access
                </button>

                <button onclick="deleteSelectedContact()" title="Delete" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-red-600 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-50 transition-all">
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
                            <input type="radio" name="selectedContact" class="w-4 h-4 text-red-800 focus:ring-red-800">
                        </th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Title</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Nick Name</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Position</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Department</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Cell Phone</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Email Work</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid From</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Valid To</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Jarvies Access</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-700 uppercase tracking-wider">Ticket Access</th>
                        <th class="w-10 px-4 py-3 text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </th>
                    </tr>
                </thead>
                <tbody id="contactTableBody" class="bg-white divide-y divide-gray-100">
                    <tr>
                        <td colspan="12" class="px-4 py-16 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <p class="text-base font-medium text-gray-900 mb-2">No contacts found</p>
                            <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new contact</small>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div id="confirmDeleteContactModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-red-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Delete Contact</h3>
            <p class="text-sm text-gray-600 text-center mb-1">Are you sure you want to delete this contact?</p>
            <p class="text-sm font-semibold text-gray-900 text-center mb-6" id="deleteContactInfo"></p>
            <div class="flex gap-3">
                <button onclick="closeConfirmDeleteContact()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button onclick="confirmDeleteContact()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Login Modal -->
<div id="revokeLoginModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Revoke Jarvies Access</h3>
            <button onclick="closeRevokeLoginModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6 space-y-3">
            <div class="flex items-center gap-3 p-3 bg-orange-50 rounded-lg border border-orange-200">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-500 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <p class="text-sm text-orange-800 font-medium">This action cannot be undone easily.</p>
            </div>
            <p class="text-sm text-gray-600">
                Revoke Jarvies login access for <strong id="revokeLoginContactName"></strong>?
                They will no longer be able to log in to Jarvies.
            </p>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-200">
            <button onclick="closeRevokeLoginModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button id="btnConfirmRevokeLogin" onclick="confirmRevokeLogin()" class="flex-1 px-4 py-2.5 bg-orange-600 text-white text-sm font-semibold rounded-lg hover:bg-orange-700 transition-all">Revoke Access</button>
        </div>
    </div>
</div>

<!-- Grant Login Modal -->
<div id="grantLoginModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Grant Jarvies Access</h3>
            <button onclick="closeGrantLoginModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm text-gray-600">
                Grant Jarvies login access to <strong id="grantLoginContactName"></strong>.
                A password setup email will be sent automatically.
            </p>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Login Email <span class="text-red-600">*</span></label>
                <input type="email" id="grantLoginEmail" required
                    placeholder="contact@company.com"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <p class="text-xs text-gray-400 mt-1">This will be the email used to log in to Jarvies.</p>
            </div>
        </div>
        <div class="flex gap-3 px-6 py-4 border-t border-gray-200">
            <button onclick="closeGrantLoginModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button id="btnConfirmGrantLogin" onclick="confirmGrantLogin()" class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-all">Grant Access</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    let contactsData = [];
    let selectedContactId = null;
    let deleteContactId = null;
    let isEditMode = false;

    /**
     * Load all contacts
     */
    async function loadContacts() {
        try {
            
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data && data.data.length > 0) {
                contactsData = data.data;
                renderContactTable(data.data);

                // Restore selection state if a contact was previously selected
                if (selectedContactId) {
                    const radio = document.querySelector(`input[name="selectedContact"][value="${selectedContactId}"]`);
                    if (radio) {
                        radio.checked = true;
                        const contact = contactsData.find(c => parseInt(c.contact_id) === parseInt(selectedContactId));
                        const btnGrant  = document.getElementById('btnGrantLogin');
                        const btnRevoke = document.getElementById('btnRevokeLogin');
                        if (contact && btnGrant && btnRevoke) {
                            if (contact.auth_user_id) {
                                btnGrant.classList.add('hidden');
                                btnRevoke.classList.remove('hidden');
                            } else {
                                btnGrant.classList.remove('hidden');
                                btnRevoke.classList.add('hidden');
                            }
                        }
                    }
                }
            } else {
                contactsData = [];
                renderEmptyContactTable();
            }
        } catch (error) {
            console.error('❌ Error loading contacts:', error);
            contactsData = [];
            renderEmptyContactTable();
        }
    }

    /**
     * Render contact table
     */
    function renderContactTable(contacts) {
        const tbody = document.getElementById('contactTableBody');
        
        tbody.innerHTML = contacts.map(contact => {
            const validFrom = contact.valid_from ? new Date(contact.valid_from).toLocaleDateString('en-GB') : '';
            const validTo = contact.valid_to ? new Date(contact.valid_to).toLocaleDateString('en-GB') : '';

            let loginBadge;
            if (contact.auth_user_id) {
                if (!contact.login_setup_done) {
                    loginBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 inline-block"></span>Pending Setup</span>`;
                } else if (contact.login_active) {
                    loginBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>Active</span>
                        <div class="text-xs text-gray-400 mt-0.5">${contact.login_email || ''}</div>`;
                } else {
                    loginBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>Inactive</span>`;
                }
            } else {
                loginBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400">No Access</span>`;
            }

            // Ticket access — toggle switch checkbox (only when contact has login account)
            let ticketAccessBadge;
            if (contact.auth_user_id) {
                const isAdmin = contact.can_view_all_tickets == 1 || contact.can_view_all_tickets === true;
                ticketAccessBadge = `
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none" onclick="event.stopPropagation()" title="${isAdmin ? 'Admin: can see all company tickets. Click to restrict.' : 'Regular: sees own tickets only. Click to grant admin.'}">
                        <div class="relative flex-shrink-0">
                            <input type="checkbox" id="cbAdmin_${contact.contact_id}" ${isAdmin ? 'checked' : ''}
                                   onchange="window.toggleViewAllTickets(${contact.contact_id})"
                                   class="sr-only peer">
                            <div class="relative w-9 h-5 bg-gray-200 rounded-full peer peer-checked:bg-blue-600 transition-colors
                                        after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                        after:bg-white after:border after:border-gray-300 after:rounded-full
                                        after:h-4 after:w-4 after:transition-all
                                        peer-checked:after:translate-x-4 peer-checked:after:border-white"></div>
                        </div>
                        <span class="text-xs font-semibold ${isAdmin ? 'text-blue-700' : 'text-gray-400'}" id="cbAdminLabel_${contact.contact_id}">${isAdmin ? 'Admin' : 'Regular'}</span>
                    </label>`;
            } else {
                ticketAccessBadge = `<span class="text-gray-300 text-xs">—</span>`;
            }

            return `
                <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="window.selectContactRow(${contact.contact_id}, event)">
                    <td class="px-4 py-3">
                        <input type="radio" name="selectedContact" value="${contact.contact_id}"
                            onclick="window.selectContact(${contact.contact_id})"
                            class="w-4 h-4 text-red-800 focus:ring-red-800">
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">${contact.title || ''}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">${contact.full_name || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${contact.nick_name || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${contact.position || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${contact.department || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${contact.cell_phone_country ? contact.cell_phone_country + ' ' : ''}${contact.cell_phone || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${contact.email_work || '-'}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validFrom}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${validTo}</td>
                    <td class="px-4 py-3">${loginBadge}</td>
                    <td class="px-4 py-3">${ticketAccessBadge}</td>
                    <td class="px-4 py-3">
                        <button onclick="window.loadContactToForm(${contact.contact_id}); event.stopPropagation();" class="text-gray-400 hover:text-gray-600">
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
    function renderEmptyContactTable() {
        const tbody = document.getElementById('contactTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="px-4 py-16 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <p class="text-base font-medium text-gray-900 mb-2">No contacts found</p>
                    <small class="text-sm text-gray-500">Fill the form above and click "Save" to add a new contact</small>
                </td>
            </tr>
        `;
    }

    /**
     * Select contact — update Grant/Revoke buttons based on login status
     */
    function selectContact(contactId) {
        selectedContactId = contactId;
        loadContactToForm(contactId);

        const contact = contactsData.find(c => parseInt(c.contact_id) === parseInt(contactId));
        const btnGrant  = document.getElementById('btnGrantLogin');
        const btnRevoke = document.getElementById('btnRevokeLogin');

        if (contact) {
            if (contact.auth_user_id) {
                btnGrant.classList.add('hidden');
                btnRevoke.classList.remove('hidden');
            } else {
                btnGrant.classList.remove('hidden');
                btnRevoke.classList.add('hidden');
            }
        }
    }

    /**
     * Select contact row
     */
    function selectContactRow(contactId, event) {
        if (event.target.tagName === 'INPUT' || event.target.tagName === 'BUTTON' || event.target.closest('button')) {
            return;
        }
        
        const radio = document.querySelector(`input[name="selectedContact"][value="${contactId}"]`);
        if (radio) {
            radio.checked = true;
            selectContact(contactId);
        }
    }

    /**
     * Load contact to form (inline editing)
     */
    async function loadContactToForm(contactId) {
        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts/${contactId}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && data.data) {
                const c = data.data;
                const fmtDate = (d) => d ? d.split('T')[0] : '';

                document.getElementById('editContactId').value           = c.contact_id;
                document.getElementById('contactTitle').value            = c.title || '';
                document.getElementById('contactName').value             = c.full_name || '';
                document.getElementById('contactNickName').value         = c.nick_name || '';
                document.getElementById('contactPosition').value         = c.position || '';
                document.getElementById('contactDepartment').value       = c.department || '';
                document.getElementById('contactEntryDate').value        = fmtDate(c.entry_date);
                document.getElementById('contactValidFrom').value        = fmtDate(c.valid_from);
                document.getElementById('contactValidTo').value          = fmtDate(c.valid_to);
                document.getElementById('contactLanguage').value         = c.language || '';
                document.getElementById('contactCellPhoneCountry').value = c.cell_phone_country || '';
                document.getElementById('contactCellPhone').value        = c.cell_phone || '';
                document.getElementById('contactTelCountry').value       = c.telephone_country || '';
                document.getElementById('contactTelephone').value        = c.telephone || '';
                document.getElementById('contactTelExt').value           = c.telephone_extension || '';
                document.getElementById('contactFaxCountry').value       = c.fax_country || '';
                document.getElementById('contactFax').value              = c.fax || '';
                document.getElementById('contactFaxExt').value           = c.fax_extension || '';
                document.getElementById('contactEmailPersonal').value    = c.email_personal || '';
                document.getElementById('contactEmailWork').value        = c.email_work || '';
                document.getElementById('contactWebsite').value          = c.website || '';
                document.getElementById('contactPreferredComm').value    = c.preferred_communication || '';
                document.getElementById('saveContactButtonText').textContent = 'Update';
            }
        } catch (error) {
            console.error('Error loading contact:', error);
        }
    }

    /**
     * Switch contact tab
     */
    function switchContactTab(tabName) {
        document.querySelectorAll('.contact-tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        document.querySelectorAll('.contact-tab').forEach(tab => {
            tab.classList.remove('border-red-800', 'text-red-800');
            tab.classList.add('border-transparent', 'text-gray-600');
        });

        document.getElementById('tab-' + tabName).classList.remove('hidden');

        const activeTab = document.querySelector(`.contact-tab[data-tab="${tabName}"]`);
        activeTab.classList.remove('border-transparent', 'text-gray-600');
        activeTab.classList.add('border-red-800', 'text-red-800');
    }


    /**
     * Save contact inline (create or update)
     */
    async function saveContactInline() {
        const fullName = document.getElementById('contactName').value;
        if (!fullName) {
            showNotification('Full Name is required', 'error');
            return;
        }
        const contactId = document.getElementById('editContactId').value;
        const isUpdate = contactId !== '';
        const contactData = {
            title: document.getElementById('contactTitle').value || null,
            full_name: fullName,
            nick_name: document.getElementById('contactNickName').value || null,
            position: document.getElementById('contactPosition').value || null,
            department: document.getElementById('contactDepartment').value || null,
            language: document.getElementById('contactLanguage').value || null,
            entry_date: document.getElementById('contactEntryDate').value || null,
            valid_from: document.getElementById('contactValidFrom').value || null,
            valid_to: document.getElementById('contactValidTo').value || null,
            cell_phone_country: document.getElementById('contactCellPhoneCountry').value || null,
            cell_phone: document.getElementById('contactCellPhone').value || null,
            telephone_country: document.getElementById('contactTelCountry').value || null,
            telephone: document.getElementById('contactTelephone').value || null,
            telephone_extension: document.getElementById('contactTelExt').value || null,
            fax_country: document.getElementById('contactFaxCountry').value || null,
            fax: document.getElementById('contactFax').value || null,
            fax_extension: document.getElementById('contactFaxExt').value || null,
            email_personal: document.getElementById('contactEmailPersonal').value || null,
            email_work: document.getElementById('contactEmailWork').value || null,
            website: document.getElementById('contactWebsite').value || null,
            preferred_communication: document.getElementById('contactPreferredComm').value || null
        };
        try {
            const url = isUpdate
                ? `/api/customers/{{ $customerId }}/contacts/${contactId}`
                : `/api/customers/{{ $customerId }}/contacts`;
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
                body: JSON.stringify(contactData)
            });
            const data = await response.json();
            if (data.success) {
                showNotification(isUpdate ? 'Contact updated successfully!' : 'Contact created successfully!', 'success');
                loadContacts();
                if (isUpdate) { loadContactToForm(contactId); } else { clearContactForm(); }
            } else {
                showNotification('Failed to save contact: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            showNotification('An error occurred while saving contact', 'error');
        }
    }

    /**
     * Clear contact form (new mode)
     */
    function clearContactForm() {
        document.getElementById('editContactId').value = '';
        ['contactTitle','contactName','contactNickName','contactPosition','contactDepartment',
         'contactLanguage','contactEntryDate','contactValidFrom','contactValidTo',
         'contactCellPhone','contactTelephone','contactTelExt','contactFax','contactFaxExt',
         'contactEmailPersonal','contactEmailWork','contactWebsite','contactPreferredComm'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('contactCellPhoneCountry').value = '+62';
        document.getElementById('contactTelCountry').value = '+62';
        document.getElementById('contactFaxCountry').value = '+62';
        const radios = document.querySelectorAll('input[name="selectedContact"]');
        radios.forEach(r => r.checked = false);
        selectedContactId = null;
        document.getElementById('saveContactButtonText').textContent = 'Save';
        showNotification('Form cleared. Ready to create new contact.', 'info');
    }

    
    /**
     * Copy selected contact
     */
    async function copySelectedContact() {
        if (!selectedContactId) {
            showNotification('Please select a contact first', 'warning');
            return;
        }

        try {
            // Fetch the selected contact data
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts/${selectedContactId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                const contact = data.data;
                
                // Create a copy with modified name
                const copyData = {
                    title: contact.title,
                    full_name: contact.full_name + ' (Copy)',
                    nick_name: contact.nick_name,
                    position: contact.position,
                    department: contact.department,
                    language: contact.language,
                    cell_phone_country: contact.cell_phone_country,
                    cell_phone: contact.cell_phone,
                    telephone_country: contact.telephone_country,
                    telephone: contact.telephone,
                    telephone_extension: contact.telephone_extension,
                    fax_country: contact.fax_country,
                    fax: contact.fax,
                    fax_extension: contact.fax_extension,
                    email_personal: contact.email_personal,
                    email_work: contact.email_work,
                    website: contact.website,
                    preferred_communication: contact.preferred_communication,
                    entry_date: contact.entry_date,
                    valid_from: contact.valid_from,
                    valid_to: contact.valid_to,
                };

                // Create the copy
                const createResponse = await fetch(`/api/customers/{{ $customerId }}/contacts`, {
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
                    showNotification('Contact copied successfully!', 'success');
                    loadContacts();
                } else {
                    showNotification('Failed to copy: ' + (createData.message || 'Unknown error'), 'error');
                }
            }
        } catch (error) {
            console.error('❌ Error copying contact:', error);
            showNotification('An error occurred while copying', 'error');
        }
    }

    /**
     * Delete selected contact
     */
    function deleteSelectedContact() {
        if (!selectedContactId) {
            showNotification('Please select a contact first', 'warning');
            return;
        }
        
        deleteContactId = selectedContactId;
        
        const contact = contactsData.find(c => c.contact_id === selectedContactId);
        let contactInfo = 'this contact';
        
        if (contact) {
            contactInfo = `${contact.full_name || 'Unknown'} (${contact.position || 'No position'})`;
        }
        
        document.getElementById('deleteContactInfo').textContent = contactInfo;
        document.getElementById('confirmDeleteContactModal').classList.remove('hidden');
        document.getElementById('confirmDeleteContactModal').classList.add('flex');
    }

    /**
     * Close delete confirmation
     */
    function closeConfirmDeleteContact() {
        document.getElementById('confirmDeleteContactModal').classList.add('hidden');
        document.getElementById('confirmDeleteContactModal').classList.remove('flex');
        deleteContactId = null;
    }

    /**
     * Confirm delete contact
     */
    async function confirmDeleteContact() {
        if (!deleteContactId) return;

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts/${deleteContactId}/delete`, {
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
                showNotification('Contact deleted successfully!', 'success');
                closeConfirmDeleteContact();
                selectedContactId = null;
                loadContacts();
                
                // Clear form fields
                document.getElementById('contactTitle').value = '';
                document.getElementById('contactName').value = '';
                document.getElementById('contactPosition').value = '';
                document.getElementById('contactCellPhone').value = '';
                document.getElementById('contactValidFrom').value = '';
                document.getElementById('contactValidTo').value = '';
            } else {
                showNotification('Failed to delete: ' + (data.message || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('❌ Error deleting contact:', error);
            showNotification('An error occurred while deleting', 'error');
        }
    }

    /**
     * Open Grant Login modal
     */
    function openGrantLoginModal() {
        if (!selectedContactId) {
            showNotification('Please select a contact first', 'warning');
            return;
        }
        const contact = contactsData.find(c => c.contact_id === selectedContactId);
        const defaultEmail = contact?.email_work || contact?.email_personal || '';
        document.getElementById('grantLoginEmail').value = defaultEmail;
        document.getElementById('grantLoginContactName').textContent = contact?.full_name || 'this contact';
        document.getElementById('grantLoginModal').classList.remove('hidden');
        document.getElementById('grantLoginModal').classList.add('flex');
    }

    function closeGrantLoginModal() {
        document.getElementById('grantLoginModal').classList.add('hidden');
        document.getElementById('grantLoginModal').classList.remove('flex');
    }

    async function confirmGrantLogin() {
        const email = document.getElementById('grantLoginEmail').value.trim();
        if (!email) {
            showNotification('Please enter a login email', 'warning');
            return;
        }

        const btn = document.getElementById('btnConfirmGrantLogin');
        btn.disabled = true;
        btn.textContent = 'Sending...';

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts/${selectedContactId}/create-login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({ login_email: email })
            });

            const data = await response.json();
            if (data.success) {
                showNotification(data.message, 'success');
                closeGrantLoginModal();
                loadContacts();
            } else {
                const errMsg = data.errors?.login_email?.[0] || data.message || 'Failed to grant access';
                showNotification(errMsg, 'error');
            }
        } catch (error) {
            console.error('❌ Error granting login:', error);
            showNotification('An error occurred', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Grant Access';
        }
    }

    /**
     * Toggle can_view_all_tickets — callable from checkbox (contactId param) or action button (uses selectedContactId)
     */
    async function toggleViewAllTickets(contactId) {
        const resolvedId = contactId || selectedContactId;
        if (!resolvedId) {
            showNotification('Please select a contact first', 'warning');
            return;
        }
        const contact = contactsData.find(c => parseInt(c.contact_id) === parseInt(resolvedId));
        if (!contact?.auth_user_id) {
            showNotification('This contact does not have a Jarvies login account', 'warning');
            return;
        }

        // Disable checkbox to prevent double-click
        const cb  = document.getElementById(`cbAdmin_${resolvedId}`);
        const btn = document.getElementById('btnToggleViewAll');
        if (cb)  cb.disabled  = true;
        if (btn) btn.disabled = true;

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts/${resolvedId}/toggle-view-all`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            if (data.success) {
                showNotification(data.message, 'success');
                loadContacts();
            } else {
                // Revert checkbox on failure
                if (cb) cb.checked = !cb.checked;
                showNotification(data.message || 'Failed to toggle access', 'error');
            }
        } catch (error) {
            if (cb) cb.checked = !cb.checked;
            showNotification('An error occurred', 'error');
        } finally {
            if (cb)  cb.disabled  = false;
            if (btn) btn.disabled = false;
        }
    }

    /**
     * Revoke login access for selected contact
     */
    function revokeLoginAccess() {
        if (!selectedContactId) {
            showNotification('Please select a contact first', 'warning');
            return;
        }
        const contact = contactsData.find(c => c.contact_id === selectedContactId);
        document.getElementById('revokeLoginContactName').textContent = contact?.full_name || 'this contact';
        const modal = document.getElementById('revokeLoginModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeRevokeLoginModal() {
        const modal = document.getElementById('revokeLoginModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    async function confirmRevokeLogin() {
        const btn = document.getElementById('btnConfirmRevokeLogin');
        btn.disabled = true;
        btn.textContent = 'Revoking...';

        try {
            const response = await fetch(`/api/customers/{{ $customerId }}/contacts/${selectedContactId}/revoke-login`, {
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
                closeRevokeLoginModal();
                showNotification(data.message, 'success');
                loadContacts();
                document.getElementById('btnRevokeLogin').classList.add('hidden');
                document.getElementById('btnGrantLogin').classList.remove('hidden');
            } else {
                showNotification(data.message || 'Failed to revoke access', 'error');
            }
        } catch (error) {
            console.error('❌ Error revoking login:', error);
            showNotification('An error occurred', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Revoke Access';
        }
    }

    // Expose functions to window for onclick handlers
    window.selectContact = selectContact;
    window.selectContactRow = selectContactRow;
    window.loadContactToForm = loadContactToForm;
    window.switchContactTab = switchContactTab;
    window.saveContactInline = saveContactInline;
    window.clearContactForm = clearContactForm;
    window.copySelectedContact = copySelectedContact;
    window.deleteSelectedContact = deleteSelectedContact;
    window.closeConfirmDeleteContact = closeConfirmDeleteContact;
    window.confirmDeleteContact = confirmDeleteContact;
    window.openGrantLoginModal = openGrantLoginModal;
    window.closeGrantLoginModal = closeGrantLoginModal;
    window.confirmGrantLogin = confirmGrantLogin;
    window.toggleViewAllTickets = toggleViewAllTickets;
    window.revokeLoginAccess = revokeLoginAccess;
    window.closeRevokeLoginModal = closeRevokeLoginModal;
    window.confirmRevokeLogin = confirmRevokeLogin;
    window.loadContacts = loadContacts;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadContacts();
    });


    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('confirmDeleteContactModal').classList.contains('hidden')) closeConfirmDeleteContact();
            if (!document.getElementById('grantLoginModal').classList.contains('hidden')) closeGrantLoginModal();
            if (!document.getElementById('revokeLoginModal').classList.contains('hidden')) closeRevokeLoginModal();
        }
    });
})();
</script>
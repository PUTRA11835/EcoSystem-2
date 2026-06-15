<div class="space-y-6">
    <!-- General Information -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">General Information</h3>
            <button onclick="saveCurrentSection()" class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                Save Changes
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Title</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Title</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="title" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Title</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mr.">Mr.</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mrs.">Mrs.</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Ms.">Ms.</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Dr.">Dr.</button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Nick Name</label>
                <input type="text" id="nickName" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Gender</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Gender</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="gender" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Gender</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Male">Male</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Female">Female</button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Religion</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Religion</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="religion" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Religion</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Islam">Islam</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Christian">Christian</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Catholic">Catholic</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Hindu">Hindu</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Buddhist">Buddhist</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Confucian">Confucian</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">First Name <span class="text-red-600">*</span></label>
                <input type="text" id="firstName" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Last Name</label>
                <input type="text" id="lastName" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Marital Status</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Status</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="maritalStatus" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Status</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Single">Single</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Married">Married</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Divorced">Divorced</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Widow/Widower">Widow/Widower</button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Birth Date</label>
                <input type="date" id="birthDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Search Term 1 <span class="text-gray-500 text-xs">(auto-generated)</span></label>
                <input type="text" id="searchTerm1" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:outline-none">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Search Term 2 <span class="text-gray-500 text-xs">(auto-generated)</span></label>
                <input type="text" id="searchTerm2" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:outline-none">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Birth Place</label>
                <input type="text" id="birthPlace" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Since Date</label>
                <input type="date" id="sinceDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>
    </div>

    <!-- Employee Information -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Employee Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Personnel Area</label>
                <input type="text" id="personnelArea" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Personnel Subarea</label>
                <input type="text" id="personnelSubarea" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Employee Group</label>
                <input type="text" id="employeeGroup" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Employee Subgroup</label>
                <input type="text" id="employeeSubgroup" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Position</label>
                <input type="text" id="position" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Division</label>
                <input type="text" id="division" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Department</label>
                <input type="text" id="department" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Authorization Group</label>
                <input type="text" id="authorizationGroup" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Home Base</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Home Base</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="homeBase" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Home Base</button>
                        @foreach(($homeBaseOptions ?? []) as $hb)
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $hb }}">{{ $hb }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Grade</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2.5 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">Select Grade</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="grade" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">Select Grade</button>
                        @foreach(($gradeOptions ?? []) as $g)
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $g }}">{{ $g }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col col-span-2">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Direct Supervision</label>
                <input type="text" id="directSupervision" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent" placeholder="e.g., ECI001">
            </div>
            <div class="flex flex-col col-span-2">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Manager</label>
                <input type="text" id="manager" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="block" class="w-5 h-5 text-red-800 border-gray-300 rounded focus:ring-red-800">
                <label for="block" class="text-sm font-semibold text-gray-700">Block Employee</label>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="deletionFlag" class="w-5 h-5 text-red-800 border-gray-300 rounded focus:ring-red-800">
                <label for="deletionFlag" class="text-sm font-semibold text-gray-700">Deletion Flag</label>
            </div>
        </div>

        <!-- Created/Changed Info -->
        <div class="grid grid-cols-2 gap-6 mt-6 pt-4 border-t border-gray-200">
            <div class="space-y-2">
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Created By</span>
                    <span id="createdBy" class="text-sm text-gray-600">-</span>
                </div>
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Created On</span>
                    <span id="createdOn" class="text-sm text-gray-600">-</span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Last Changed By</span>
                    <span id="lastChangedBy" class="text-sm text-gray-600">-</span>
                </div>
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Last Changed On</span>
                    <span id="lastChangedOn" class="text-sm text-gray-600">-</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate search terms from names
document.getElementById('firstName')?.addEventListener('input', function() {
    const searchTerm1 = document.getElementById('searchTerm1');
    if (searchTerm1) {
        searchTerm1.value = this.value.toUpperCase();
    }
});

document.getElementById('lastName')?.addEventListener('input', function() {
    const searchTerm2 = document.getElementById('searchTerm2');
    if (searchTerm2) {
        searchTerm2.value = this.value.toUpperCase();
    }
});

</script>
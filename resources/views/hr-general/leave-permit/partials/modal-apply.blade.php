<!-- Apply / Submit Leave & Permit Modal -->
<div id="modalApplyLeavePermit" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl sm:max-w-3xl max-h-[90vh] overflow-y-auto transform transition-all">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between primary-gradient text-white sticky top-0 z-10">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-paper-plane text-lg"></i>
                <h3 class="font-bold text-base" id="modalApplyTitle">Apply Leave / Permit</h3>
            </div>
            <button onclick="closeApplyModal()" class="text-white opacity-80 hover:opacity-100 transition-opacity">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form id="formApplyLeavePermit" onsubmit="handleApplySubmit(event)" class="p-6 space-y-5" enctype="multipart/form-data">
            <input type="hidden" id="applyAppId" value="">

            @if(isset($isHR) && $isHR)
            <!-- ── Employee Selector for HR: Searchable Dropdown (search is inside the dropdown) ── -->
            <div class="bg-red-50 border border-red-100 rounded-xl p-4 space-y-2">
                <label class="block text-xs font-bold text-red-900 uppercase tracking-wider">
                    <i class="fas fa-user-cog text-red-600 mr-1"></i> Select Employee
                    <span class="text-[10px] text-red-400 normal-case font-normal ml-1">(search by name or Employee ID)</span>
                </label>

                <!-- Hidden value holder -->
                <input type="hidden" id="applyEmployeeId">

                <!-- The toggle button (mirrors Bootstrap's data-bs-toggle="dropdown" pattern) -->
                <div class="relative" id="empDropdownWrapper">
                    <button type="button" id="empDropdownBtn"
                        onclick="toggleEmpDropdown()"
                        class="w-full flex items-center justify-between gap-2 bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs text-left hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-500 transition-all">
                        <span class="flex items-center gap-2 min-w-0">
                            <i class="fas fa-user-circle text-gray-400 flex-shrink-0"></i>
                            <span id="empDropdownBtnLabel" class="truncate text-gray-500 font-medium">Select an employee...</span>
                        </span>
                        <i id="empDropdownChevron" class="fas fa-caret-down text-gray-400 flex-shrink-0 transition-transform duration-200"></i>
                    </button>

                    <!-- Dropdown panel: search at top, filtered list below -->
                    <ul id="empDropdownList"
                        class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl hidden"
                        style="max-height: 260px; display: flex; flex-direction: column;">

                        <!-- Search input inside the dropdown (key feature) -->
                        <li class="px-2 pt-2 pb-1 border-b border-gray-100 sticky top-0 bg-white z-10 flex-shrink-0">
                            <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-2.5 py-1.5 bg-gray-50 focus-within:ring-1 focus-within:ring-red-400 focus-within:border-red-400">
                                <i class="fas fa-search text-gray-400 text-[10px]"></i>
                                <input type="text" id="applyEmployeeSearch"
                                    placeholder="Search name or employee ID..."
                                    autofocus
                                    autocomplete="off"
                                    class="flex-1 text-xs bg-transparent focus:outline-none text-gray-800 placeholder-gray-400 min-w-0"
                                    oninput="filterEmpDropdown()">
                                <button type="button" onclick="document.getElementById('applyEmployeeSearch').value=''; filterEmpDropdown();" class="text-gray-300 hover:text-gray-500 text-[10px]">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </li>

                        <!-- Filtered options list -->
                        <div id="empDropdownItems" class="overflow-y-auto flex-1 py-1">
                            <!-- Populated by JS -->
                        </div>
                        <li id="empDropdownEmpty" class="px-4 py-3 text-xs text-gray-400 text-center hidden list-none">
                            <i class="fas fa-search-minus mr-1"></i> No employees found.
                        </li>
                    </ul>
                </div>

                <!-- Selected employee confirmation badge -->
                <div id="empSelectedBadge" class="hidden items-center gap-2 bg-white border border-green-200 rounded-lg px-3 py-1.5 text-xs">
                    <i class="fas fa-check-circle text-green-500 flex-shrink-0"></i>
                    <span id="empSelectedName" class="font-semibold text-gray-800 flex-1"></span>
                    <span id="empSelectedEci" class="text-gray-400 font-mono text-[10px]"></span>
                </div>
            </div>
            @endif

            <!-- Leave / Permit Type -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Leave / Permit Type <span class="text-red-500">*</span>
                </label>
                <select id="applyTypeId" required class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all" onchange="onTypeSelectChange()">
                    <option value="" disabled selected>-- Select Leave / Permit Type --</option>
                    @if(isset($activeTypes) && count($activeTypes) > 0)
                        @foreach($activeTypes as $t)
                            <option value="{{ is_object($t) ? $t->id : $t['id'] }}" 
                                    data-code="{{ is_object($t) ? $t->code : $t['code'] }}"
                                    data-requires-attachment="{{ (is_object($t) ? $t->requires_attachment : !empty($t['requires_attachment'])) ? '1' : '0' }}">
                                {{ is_object($t) ? $t->name : $t['name'] }} ({{ is_object($t) ? $t->code : $t['code'] }}) - {{ strtoupper(is_object($t) ? $t->category : $t['category']) }}
                            </option>
                        @endforeach
                    @endif
                </select>
                <span id="quotaHintText" class="text-xs text-gray-500 mt-1 block hidden"></span>
            </div>

            <!-- Leave Duration Mode (Full Day vs Half Day) -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Leave Duration Mode <span class="text-red-500">*</span>
                </label>
                <select id="applyDayType" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all" onchange="onDayTypeChange()">
                    <option value="full" selected>Full Day (1.0 Day / Multi-day)</option>
                    <option value="half">Half-Day Leave (0.5 Day)</option>
                </select>
            </div>

            <!-- Half-Day WFH Continuation Prompt (Shown only when Half-Day is selected) -->
            <div id="wfhPromptWrapper" class="p-3.5 rounded-xl border border-indigo-200 bg-indigo-50/80 space-y-2 hidden">
                <label class="block text-xs font-bold text-indigo-950 uppercase tracking-wider">
                    <i class="fas fa-laptop-house text-indigo-600 mr-1"></i> Work From Home (WFH) Continuation Prompt <span class="text-red-500">*</span>
                </label>
                <p class="text-xs text-indigo-800 font-medium">Please indicate whether work-from-home (WFH) will continue or not:</p>
                <select id="applyWfhOption" class="w-full text-xs sm:text-sm border border-indigo-300 rounded-lg px-3 py-2.5 bg-white focus:ring-2 focus:ring-indigo-500 font-semibold text-gray-800">
                    <option value="wfh_continue">Continue working via WFH (Work From Home for remaining half day)</option>
                    <option value="wfh_off">No WFH / Off (Not working for the remainder of the day)</option>
                </select>
            </div>

            <!-- Date Range Selectors -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        Start Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="applyStartDate" required min="{{ isset($isHR) && $isHR ? '' : date('Y-m-d') }}" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all" onchange="calculateDaysPreview()">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                        End Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="applyEndDate" required min="{{ isset($isHR) && $isHR ? '' : date('Y-m-d') }}" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all" onchange="calculateDaysPreview()">
                </div>
            </div>

            <!-- Total Days Preview Badge -->
            <div id="daysCountBadge" class="p-3 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-between text-xs hidden">
                <span class="font-semibold text-gray-600">Calculated Business Days:</span>
                <span class="font-bold text-red-700 text-sm" id="daysCountValue">0 days</span>
            </div>

            <!-- Quota Exceeded Warning Banner -->
            <div id="applyQuotaWarningBanner" class="p-3.5 rounded-xl border border-red-200 bg-red-50 text-red-800 text-xs hidden items-center gap-2.5">
                <i class="fas fa-exclamation-triangle text-red-600 text-base flex-shrink-0"></i>
                <div>
                    <strong class="block">Quota Limit Exceeded!</strong>
                    <span id="applyQuotaWarningText" class="text-[11px]">The requested duration exceeds your available quota balance for this leave type. Application cannot be submitted.</span>
                </div>
            </div>

            <!-- Reason / Purpose -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Reason / Purpose <span class="text-red-500">*</span>
                </label>
                <textarea id="applyReason" rows="3" required placeholder="State your detailed reason..." class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all"></textarea>
            </div>

            <!-- File Attachment -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Attachment <span id="attachmentRequiredAsterisk" class="text-red-500 hidden">* (Required for Doctor Note / Event)</span>
                </label>
                <input type="file" id="applyAttachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="validateAttachmentSize(this)" class="w-full text-xs border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                <p class="text-[11px] text-gray-500 mt-1">Accepted formats: PDF, JPG, PNG, DOC (<strong>Max 1MB</strong>). Required for doctor's note or medical events.</p>
            </div>

            <!-- Footer Buttons -->
            <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeApplyModal()" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" id="btnSubmitApply" class="px-6 py-2.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function validateAttachmentSize(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const maxSizeInBytes = 1024 * 1024; // 1MB
            if (file.size > maxSizeInBytes) {
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                input.value = '';
                if (typeof showToast === 'function') {
                    showToast(`Attachment file size exceeds 1MB limit (Current file: ${sizeMB} MB). Please select a file smaller than 1MB.`, 'warning');
                }
            }
        }
    }
</script>

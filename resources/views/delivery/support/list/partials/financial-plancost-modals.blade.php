{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- PLAN COST — ADD / EDIT COST ITEM MODAL                         --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="costModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPlanCost.closeModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900" id="costModalTitle">Add Cost Item</h3>
                <button type="button" onclick="SupportPlanCost.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="costModalMode"     value="create">
                <input type="hidden" id="costModalId"       value="">
                <input type="hidden" id="costModalParentId" value="">
                <div id="costTypeRow">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cost Type <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="costTypeRadio" id="costTypeIndirect" value="indirect"
                                   class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500"
                                   onchange="SupportPlanCost.onTypeChange('indirect')">
                            <span class="text-sm text-gray-700 font-medium">Indirect Cost</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="costTypeRadio" id="costTypeDirect" value="direct" checked
                                   class="w-4 h-4 text-red-600 border-gray-300 focus:ring-red-500"
                                   onchange="SupportPlanCost.onTypeChange('direct')">
                            <span class="text-sm text-gray-700 font-medium">Direct Cost</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-xs text-gray-400">(optional, e.g. 210)</span></label>
                    <input type="text" id="costCodeInput" maxlength="20"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. 210, 220, 230 …">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Name <span class="text-red-500">*</span></label>
                    <input type="text" id="costNameInput" maxlength="200"
                           class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus"
                           placeholder="e.g. PROJECT ALLOWANCE, TRAVELING …">
                </div>
                <div id="costAggregateNotice" class="hidden rounded-lg bg-amber-50 border border-amber-200 p-3">
                    <div class="flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs text-amber-700 leading-relaxed">
                            <span class="font-semibold">Budget, Release, and Actual</span> for this item are automatically calculated from its sub-items.<br>
                            To change the values, edit each sub-item listed below.
                        </p>
                    </div>
                </div>
                <div id="costAmountsSection">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Budget (Rp)</label>
                            <input type="text" id="costBudgetInput" inputmode="numeric"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                                   placeholder="0">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center gap-1">
                                Release (Rp)
                                <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                            </label>
                            <input type="text" id="costReleaseInput" inputmode="numeric"
                                   class="block w-full py-2.5 px-3 border border-gray-300 rounded-md shadow-sm text-sm primary-focus text-right"
                                   placeholder="0">
                        </div>
                    </div>
                    <div class="mt-3 flex items-start gap-2 text-xs text-gray-500">
                        <span class="inline-block w-3 h-3 rounded-full bg-orange-500 mt-0.5 flex-shrink-0"></span>
                        <span>
                            <span class="font-medium text-orange-700">Actual</span> is calculated automatically
                            from the total of the expense details. Click the
                            <span class="font-medium">Actual</span> column on the table to add or view expenses.
                        </span>
                    </div>
                    <div class="mt-3 bg-gray-50 rounded-lg p-3 text-xs text-gray-600 grid grid-cols-2 gap-2">
                        <div>
                            <span class="font-medium text-green-700">Avail. Budget</span> (Budget − Release):
                            <span id="previewAvailBudget" class="font-semibold ml-1 text-green-700">—</span>
                        </div>
                        <div>
                            <span class="font-medium text-teal-700">Avail. Release</span> (Release − Actual):
                            <span id="previewAvailRelease" class="font-semibold ml-1 text-teal-700">—</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" onclick="SupportPlanCost.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="costModalSaveBtn" onclick="SupportPlanCost.save()"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Delete Cost Confirm Modal --}}
<div id="costDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPlanCost.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Cost Item?</h3>
                <p class="text-sm text-gray-500 mb-1">Item "<span id="costDeleteName" class="font-medium text-gray-700"></span>" will be deleted.</p>
                <p class="text-xs text-red-500 mb-5">If this item has sub-items, all sub-items will also be deleted.</p>
                <input type="hidden" id="costDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="SupportPlanCost.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" onclick="SupportPlanCost.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Delete Expense Confirm Modal --}}
<div id="expenseDeleteModal" class="fixed inset-0 z-[55] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPlanCost.closeExpenseDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Expense?</h3>
                <p class="text-sm text-gray-500 mb-1">Expense "<span id="expenseDeleteName" class="font-medium text-gray-700"></span>" will be deleted.</p>
                <p class="text-xs text-red-500 mb-5">The actual amount will be recalculated automatically.</p>
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="SupportPlanCost.closeExpenseDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="expenseDeleteConfirmBtn" onclick="SupportPlanCost.confirmDeleteExpense()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Expense Modal --}}
<div id="expenseEditModal" class="fixed inset-0 z-[55] hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPlanCost.closeExpenseEditModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col" style="max-height:90vh;">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900">Edit Expense</h3>
                <button type="button" onclick="SupportPlanCost.closeExpenseEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="overflow-y-auto flex-1 p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expense Name <span class="text-red-500">*</span></label>
                    <input type="text" id="aeDescInput" maxlength="200"
                           class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                           placeholder="e.g. Transportation, Accommodation…">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rp) <span class="text-red-500">*</span></label>
                    <input type="text" id="aeAmountInput" inputmode="numeric"
                           class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                           placeholder="0">
                </div>
                <div id="aeCurrentDocRow" class="hidden">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Current Document</label>
                    <div class="flex items-center justify-between gap-2 border border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
                        <a id="aeCurrentDocLink" href="#" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline truncate">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            <span id="aeCurrentDocName" class="truncate">View</span>
                        </a>
                        <button type="button" onclick="SupportPlanCost.removeEditDoc()"
                                class="text-xs text-red-500 hover:text-red-700 font-medium flex-shrink-0">Remove</button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        <span id="aeDropTitle">Supporting Document</span>
                        <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <div id="aeDropZone"
                         class="border-2 border-dashed border-gray-300 rounded-lg py-4 px-4 text-center cursor-pointer hover:border-orange-300 hover:bg-orange-50/30 transition-all duration-200"
                         onclick="document.getElementById('aeFileInput').click()"
                         ondragover="event.preventDefault();this.classList.add('border-orange-400','bg-orange-50/40')"
                         ondragleave="this.classList.remove('border-orange-400','bg-orange-50/40')"
                         ondrop="SupportPlanCost.handleEditDocDrop(event)">
                        <svg class="w-6 h-6 text-gray-300 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        <p class="text-xs text-gray-400" id="aeDropLabel">Click or drag &amp; drop proof document</p>
                        <input type="file" id="aeFileInput" class="hidden"
                               onchange="SupportPlanCost.onEditFileSelected(this)">
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="SupportPlanCost.closeExpenseEditModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="aeSaveBtn" onclick="SupportPlanCost.saveEditExpense()"
                        class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- PLAN COST — ACTUAL DETAIL MODAL --}}
<div id="actualDetailModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPlanCost.closeActualDetailModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-4xl flex flex-col" style="max-height:90vh;">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Actual Expense Details</h3>
                    <p class="text-xs text-gray-500 mt-0.5" id="actualDetailSubtitle"></p>
                </div>
                <button type="button" onclick="SupportPlanCost.closeActualDetailModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="overflow-y-auto flex-1 p-6 space-y-5">
                <div id="actualDetailSummary" class="flex justify-center">
                    <div class="bg-orange-50 border border-orange-200 rounded-lg px-6 py-4 text-center w-full max-w-xs">
                        <p class="text-xs text-orange-600 font-medium uppercase tracking-wide mb-1">Actual Amount</p>
                        <p class="text-lg font-bold text-orange-700 font-mono" id="adTotalItems">Rp 0</p>
                        <p class="text-xs text-orange-400 mt-0.5">Auto-calculated from the expense total below</p>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Expense List</h4>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-600">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-medium">#</th>
                                    <th class="px-4 py-2.5 text-left font-medium">Expense Name</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Amount</th>
                                    <th class="px-4 py-2.5 text-center font-medium">Document</th>
                                    <th class="px-4 py-2.5 text-center font-medium w-24">Action</th>
                                </tr>
                            </thead>
                            <tbody id="actualDetailTableBody" class="divide-y divide-gray-100">
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-gray-400 text-sm">
                                        <svg class="animate-spin h-5 w-5 primary-text mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Loading data…
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="actualDetailTableFoot" class="hidden bg-gray-50 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="2" class="px-4 py-2.5 text-sm font-bold text-gray-700 text-right">Total:</td>
                                    <td class="px-4 py-2.5 text-right font-bold text-blue-700 font-mono text-sm" id="adFooterTotal">Rp 0</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="border border-dashed border-gray-300 rounded-xl p-4 bg-gray-50/60">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Expense
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Expense Name <span class="text-red-500">*</span></label>
                            <input type="text" id="adDescInput" maxlength="200"
                                   class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                                   placeholder="e.g. Transportation, Accommodation…">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rp) <span class="text-red-500">*</span></label>
                            <input type="text" id="adAmountInput" inputmode="numeric"
                                   class="block w-full py-2 px-3 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400"
                                   placeholder="0">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Supporting Document
                                <span class="font-normal text-gray-400">(optional)</span>
                            </label>
                            <div id="adDropZone"
                                 class="border-2 border-dashed border-gray-300 rounded-lg py-4 px-4 text-center cursor-pointer hover:border-orange-300 hover:bg-orange-50/30 transition-all duration-200"
                                 onclick="document.getElementById('adFileInput').click()"
                                 ondragover="event.preventDefault();this.classList.add('border-orange-400','bg-orange-50/40')"
                                 ondragleave="this.classList.remove('border-orange-400','bg-orange-50/40')"
                                 ondrop="SupportPlanCost.handleDocDrop(event)">
                                <svg class="w-6 h-6 text-gray-300 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <p class="text-xs text-gray-400" id="adDropLabel">Click or drag &amp; drop proof document</p>
                                <input type="file" id="adFileInput" class="hidden"
                                       onchange="SupportPlanCost.onFileSelected(this)">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" id="adAddBtn" onclick="SupportPlanCost.addExpenseItem()"
                                class="inline-flex items-center gap-2 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition disabled:opacity-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add
                        </button>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end flex-shrink-0">
                <button type="button" onclick="SupportPlanCost.closeActualDetailModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- TERM OF PAYMENT (TOP) — ADD / EDIT MODAL                       --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div id="paymentTermModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPaymentTermPlan.closeModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between flex-shrink-0">
                <h3 class="text-base font-semibold text-gray-900" id="paymentTermModalTitle">Add Payment Term</h3>
                <button type="button" onclick="SupportPaymentTermPlan.closeModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto space-y-4">
                <input type="hidden" id="paymentTermModalMode" value="create">
                <input type="hidden" id="paymentTermModalId" value="">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Term <span class="text-red-500">*</span></label>
                    <input type="text" id="pt_payment_term" maxlength="255" autocomplete="off"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                           placeholder="e.g. Down Payment, Termin 1, Final Payment">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment % <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" id="pt_payment_percentage" min="0" max="100" step="0.01" autocomplete="off"
                                   oninput="SupportPaymentTermPlan.recalcAmount()"
                                   class="w-full pr-9 pl-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus text-right"
                                   placeholder="0">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 pointer-events-none">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-gray-400 font-normal">(auto)</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">Rp.</span>
                            <input type="text" id="pt_amount_disp" readonly tabindex="-1"
                                   class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg bg-gray-50 cursor-not-allowed text-sm text-gray-600 text-right"
                                   placeholder="0">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Requirements / Evidence</label>
                    <textarea id="pt_requirements" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus resize-none"
                              placeholder="e.g. Signed BAST, Invoice, PO number…"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Date</label>
                        <input type="text" id="pt_estimated_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                               placeholder="dd/mm/yyyy">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Submit Invoice Date</label>
                        <input type="text" id="pt_submit_invoice_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                               placeholder="dd/mm/yyyy">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Invoice Number <span id="pt_invoice_number_req" class="text-red-500 hidden">*</span>
                    </label>
                    <input type="text" id="pt_invoice_number" maxlength="255" autocomplete="off"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                           placeholder="e.g. INV/2026/06/001">
                    <p id="pt_invoice_number_hint" class="mt-1 text-xs text-gray-400 hidden">Required because Submit Invoice Date is filled.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Paid Date <span id="pt_paid_date_req" class="text-red-500 hidden">*</span>
                        </label>
                        <input type="text" id="pt_paid_date" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus"
                               placeholder="dd/mm/yyyy">
                        <p id="pt_paid_date_hint" class="mt-1 text-xs text-gray-400 hidden">Required because Status is Paid.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="pt_status" onchange="SupportPaymentTermPlan.togglePaidDateRequired()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus">
                            @foreach(['Open','Paid','Delay'] as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 flex-shrink-0">
                <button type="button" onclick="SupportPaymentTermPlan.closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="button" id="paymentTermSaveBtn" onclick="SupportPaymentTermPlan.save()"
                        class="px-4 py-2 text-sm font-semibold text-white primary-gradient rounded-lg hover:opacity-90 transition disabled:opacity-50">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- TERM OF PAYMENT (TOP) — DELETE CONFIRMATION MODAL --}}
<div id="paymentTermDeleteModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop fixed inset-0 bg-black bg-opacity-50" onclick="SupportPaymentTermPlan.closeDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">Delete Payment Term #<span id="ptDeleteNumber"></span>?</h3>
                <p class="text-sm text-gray-500 mb-5">This payment term will be permanently deleted.</p>
                <input type="hidden" id="ptDeleteId" value="">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="SupportPaymentTermPlan.closeDeleteModal()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="button" id="ptDeleteConfirmBtn" onclick="SupportPaymentTermPlan.confirmDelete()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

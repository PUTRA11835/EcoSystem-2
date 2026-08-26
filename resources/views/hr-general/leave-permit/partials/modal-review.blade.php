<!-- Review / Approval Modal (HR & Employee View) -->
<div id="modalReviewLeavePermit"
    class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div
        class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl sm:max-w-3xl max-h-[90vh] overflow-y-auto transform transition-all">
        <!-- Header -->
        <div
            class="px-6 py-4 border-b border-gray-100 flex items-center justify-between primary-gradient text-white sticky top-0 z-10">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-clipboard-check text-lg"></i>
                <h3 class="font-bold text-base">Application Review Details</h3>
            </div>
            <button onclick="closeReviewModal()" class="text-white opacity-80 hover:opacity-100 transition-opacity">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Details & Review Actions -->
        <div class="p-6 space-y-5">
            <input type="hidden" id="reviewAppId" value="">

            <!-- Info Summary Box -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-3 text-xs">
                <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                    <div>
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider block">Application
                            Reference</span>
                        <span class="font-bold text-gray-900 text-sm" id="reviewAppNo">-</span>
                    </div>
                    <div id="reviewStatusBadge"></div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-1">
                    <div>
                        <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Employee</span>
                        <span class="font-semibold text-gray-800 text-xs mt-0.5 block" id="reviewEmpName">-</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Leave
                            Type</span>
                        <span class="font-semibold text-gray-800 text-xs mt-0.5 block" id="reviewTypeName">-</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Period
                            Dates</span>
                        <span class="font-semibold text-gray-800 text-xs mt-0.5 block" id="reviewDates">-</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Total
                            Duration</span>
                        <span class="font-bold text-red-600 text-xs mt-0.5 block" id="reviewTotalDays">-</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-gray-200">
                    <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Reason /
                        Application Purpose</span>
                    <p class="text-gray-700 mt-1 whitespace-pre-line leading-relaxed text-xs" id="reviewReason">-</p>
                </div>
                <div id="reviewAttachmentWrapper" class="pt-2 border-t border-gray-200 hidden">
                    <span class="text-gray-400 block text-[10px] uppercase font-bold tracking-wider">Supporting
                        Attachment</span>
                    <a id="reviewAttachmentLink" href="#" target="_blank"
                        class="inline-flex items-center gap-1.5 text-red-600 hover:text-red-700 font-semibold mt-1 bg-red-50 border border-red-100 rounded-lg px-3 py-1.5 text-xs">
                        <i class="fas fa-paperclip text-xs"></i>
                        <span>View Attachment Document</span>
                    </a>
                </div>
            </div>

            @if(isset($isHR) && $isHR)
                <!-- Notes Input Area for Actions (HR Only) -->
                <div id="reviewNoteWrapper" class="space-y-1.5">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider">HR Notes / Feedback
                        <span id="reviewNoteRequired" class="text-red-500 hidden">*</span></label>
                    <textarea id="reviewNoteText" rows="3"
                        placeholder="Add notes, revision instructions, or rejection reasons..."
                        class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all"></textarea>
                </div>
            @endif

            <!-- Audit Logs History Timeline -->
            <div class="pt-2">
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Application Activity Log</h4>
                <div id="reviewLogTimeline"
                    class="space-y-2 max-h-40 overflow-y-auto border border-gray-200 rounded-xl p-3.5 bg-gray-50 text-[11px]">
                    <!-- Logs populated via JS -->
                </div>
            </div>

            @if(isset($isHR) && $isHR)
                <!-- HR Action Buttons -->
                <div class="pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2">
                    <button type="button" id="btnHREditOverride" onclick="openHREditModal()"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="fas fa-pencil-alt text-gray-500"></i> Edit Details (HR Override)
                    </button>
                    <div id="reviewHRActionButtons" class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="confirmReviewAction('reject')"
                            class="px-4 py-2 bg-red-100 text-red-700 hover:bg-red-200 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                            <i class="fas fa-times-circle"></i> Reject
                        </button>
                        <button type="button" onclick="confirmReviewAction('revision')"
                            class="px-4 py-2 bg-yellow-100 text-yellow-800 hover:bg-yellow-200 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                            <i class="fas fa-undo"></i> Ask for Edit
                        </button>
                        <button type="button" onclick="confirmReviewAction('approve')"
                            class="px-5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm flex items-center gap-1.5">
                            <i class="fas fa-check-circle"></i> Approve
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Permanent Action Confirmation Warning Modal -->
<div id="modalConfirmReviewAction"
    class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden p-6 text-center space-y-4">
        <div
            class="w-12 h-12 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto text-xl">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <h4 class="font-bold text-base text-gray-900" id="confirmReviewModalTitle">Confirm Action</h4>
            <p class="text-xs text-gray-500 mt-1" id="confirmReviewModalMessage">
                Are you sure you want to approve/reject this application? This action is permanent and cannot be edited
                afterwards by employees.
            </p>
        </div>
        <div class="flex items-center justify-center gap-3 pt-2">
            <button type="button" onclick="closeConfirmReviewModal()"
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                Cancel
            </button>
            <button type="button" id="btnConfirmReviewActionSubmit" onclick="executePendingReviewAction()"
                class="px-5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                Yes, Proceed
            </button>
        </div>
    </div>
</div>
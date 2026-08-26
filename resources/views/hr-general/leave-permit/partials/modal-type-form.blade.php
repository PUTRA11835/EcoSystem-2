<!-- Master Leave Type Form Modal (HR & Admin CRUD) -->
<div id="modalMasterTypeForm" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl sm:max-w-2xl overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between primary-gradient text-white">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-layer-group text-lg"></i>
                <h3 class="font-bold text-base" id="modalTypeFormTitle">Add Master Leave Type</h3>
            </div>
            <button onclick="closeTypeFormModal()" class="text-white opacity-80 hover:opacity-100 transition-opacity">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form id="formMasterType" onsubmit="handleTypeFormSubmit(event)" class="p-6 space-y-4">
            <input type="hidden" id="typeFormId" value="">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Code <span class="text-red-500">*</span></label>
                    <input type="text" id="typeFormCode" required placeholder="e.g. CTH" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 uppercase">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Category <span class="text-red-500">*</span></label>
                    <select id="typeFormCategory" required class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500">
                        <option value="leave">Leave (Cuti)</option>
                        <option value="permit">Permit (Izin)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Leave Type Name <span class="text-red-500">*</span></label>
                <input type="text" id="typeFormName" required placeholder="e.g. Cuti Tahunan" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Default Quota (Days) <span class="text-red-500">*</span></label>
                    <input type="number" id="typeFormDefaultQuota" required min="0" step="0.5" placeholder="e.g. 12" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Min Service Period</label>
                    <input type="text" id="typeFormMinService" placeholder="e.g. 12 bln" class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Paid Status <span class="text-red-500">*</span></label>
                    <select id="typeFormIsPaid" required class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500">
                        <option value="1">Paid (Ya / Berbayar)</option>
                        <option value="0">Unpaid (Potong Gaji)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Gender Target <span class="text-red-500">*</span></label>
                    <select id="typeFormGenderTarget" required class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500">
                        <option value="all">Everyone (All)</option>
                        <option value="P">Perempuan (P)</option>
                        <option value="L">Laki-Laki (L)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-4 text-xs pt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="typeFormRequiresAttachment" class="rounded text-red-600 focus:ring-red-500">
                    <span class="font-medium text-gray-700">Requires File Attachment</span>
                </label>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Description</label>
                <textarea id="typeFormDescription" rows="2" placeholder="Policy explanation..." class="w-full text-xs sm:text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500"></textarea>
            </div>

            <!-- Footer Actions -->
            <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeTypeFormModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                    Save Type
                </button>
            </div>
        </form>
    </div>
</div>

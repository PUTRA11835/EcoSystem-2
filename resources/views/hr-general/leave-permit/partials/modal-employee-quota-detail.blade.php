<!-- Employee Quota Detailed Breakdown Modal (HR Only - Requirement 8) -->
<div id="modalEmployeeQuotaDetail" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl sm:max-w-3xl overflow-hidden transform transition-all">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between primary-gradient text-white">
            <div class="flex items-center gap-2.5">
                <i class="fas fa-id-card-alt text-lg"></i>
                <div>
                    <h3 class="font-bold text-base" id="empQuotaDetailTitle">Employee Quota Details</h3>
                    <p class="text-[11px] text-white/80" id="empQuotaDetailSubtitle">-</p>
                </div>
            </div>
            <button onclick="closeEmployeeQuotaDetailModal()" class="text-white opacity-80 hover:opacity-100 transition-opacity">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Body Table -->
        <div class="p-6 space-y-4">
            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-2.5">Type</th>
                            <th class="px-3 py-2.5 text-center">Rule / Reset</th>
                            <th class="px-3 py-2.5 text-right">Allocated</th>
                            <th class="px-3 py-2.5 text-right">Used</th>
                            <th class="px-3 py-2.5 text-right">Pending</th>
                            <th class="px-4 py-2.5 text-right font-bold">Remaining</th>
                        </tr>
                    </thead>
                    <tbody id="tblEmpQuotaDetailBody" class="divide-y divide-gray-100 text-gray-700">
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Loading quota details...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Action -->
            <div class="pt-2 flex justify-end">
                <button type="button" onclick="closeEmployeeQuotaDetailModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

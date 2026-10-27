{{-- Kartu kontak darurat (tabel profil HR; tombol Save sendiri, grup 'emergency' di hrprofile.blade.php).
     Internal: di tab Family. Konsultan External: di tab Basic Data (tab Family tidak berlaku bagi konsultan); OPSIONAL, tidak disyaratkan untuk kontrak. --}}
{{-- HC-D54: kontak darurat (ESH) — disimpan lewat tabel profil HR; tombol Save sendiri. --}}
<div class="border border-gray-200 rounded-xl p-4">
    <div class="flex items-center justify-between mb-3">
        <div>
            <h5 class="text-sm font-bold text-gray-900">Emergency Contact</h5>
            <p class="text-xs text-gray-500 mt-0.5">Who should we contact in an emergency?{{ !empty($emergencyOptional) ? ' Optional for consultants.' : ' Separate from the family members listed below.' }}</p>
        </div>
        <button type="button" id="hrEcSaveBtn" onclick="hrSaveGroup('emergency')" class="js-section-action px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900">
            <span id="hrEcSaveBtnText">Save</span>
        </button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name</label>
            <input type="text" id="hrEcName" data-field="emergency_contact_name" data-hr-group="emergency" maxlength="150" @disabled(!empty($hrRo)) class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-red-600 mt-1 hidden" data-error-for="emergency_contact_name"></p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Relationship</label>
            <input type="text" id="hrEcRel" data-field="emergency_contact_relation" data-hr-group="emergency" maxlength="50" placeholder="e.g. Spouse, Parent" @disabled(!empty($hrRo)) class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-red-600 mt-1 hidden" data-error-for="emergency_contact_relation"></p>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Phone</label>
            <input type="text" id="hrEcPhone" data-field="emergency_contact_phone" data-hr-group="emergency" maxlength="30" placeholder="e.g. 0812-3456-7890" @disabled(!empty($hrRo)) class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <p class="text-xs text-red-600 mt-1 hidden" data-error-for="emergency_contact_phone"></p>
        </div>
    </div>
</div>

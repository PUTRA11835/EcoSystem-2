{{-- Foto, tanda tangan & informasi HR (H3.6; ditampilkan DI DALAM tab Basic Data — HC-D51). Data dari GET /api/employees/{id}/hr-profile.
     Dipakai di Master > Employee > detail DAN My Profile; izin dipilih server menurut target (lihat
     CheckEmployeeSectionAccess). Tampilan menyesuaikan jenis karyawan (Internal / External) mengikuti ESH:
       Internal : status kepegawaian, grade, akhir probation, catatan HR (bagian ini)
       External : foto, tanda tangan, engagement, catatan HR (form konsultan ESH tidak memiliki field Internal tadi)
     HC-D54: golongan darah + ibu kandung ada di Basic Data › General Information; kontak darurat di tab Family
     (fragmen kecil di berkas masing-masing, grup `personal` / `emergency`). Seluruhnya memakai skrip di berkas INI.
     Field kepegawaian & catatan HR hanya dapat diubah HR; pemilik mengubah data pribadi. Penegakan di SERVER —
     di sini hanya penyajian. --}}
@php
    $hrIsSelf = (int) session('user.id') === (int) ($employee->id ?? 0);
    $hrType   = ($employee->employee_type ?? null) ?: 'Internal';
    $hrReadonly = isset($isReadonly) && $isReadonly;
@endphp
<div id="hrProfileRoot" class="space-y-6 {{ $hrReadonly ? 'profile-readonly' : '' }}"
     data-employee-id="{{ (int) $employee->id }}" data-is-self="{{ $hrIsSelf ? '1' : '0' }}" data-type="{{ $hrType }}">

    <div class="flex justify-between items-center pb-2 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-gray-900">Photo, signature &amp; HR information</h3>
            <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $hrType === 'External' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700' }}">{{ $hrType }}</span>
            @if($hrReadonly)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-medium"><i class="fas fa-lock text-[10px]"></i> View Only</span>
            @endif
        </div>
        <div class="flex gap-2 js-section-action">
            <button type="button" id="hrSaveBtn" onclick="hrSave()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900 transition-all">
                <i class="fas fa-save text-xs"></i> <span id="hrSaveBtnText">Save</span>
            </button>
        </div>
    </div>

    {{-- ── Foto & tanda tangan (semua jenis) ── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="border border-gray-200 rounded-xl p-4">
            <h5 class="text-sm font-bold text-gray-900 mb-1">Profile photo</h5>
            <p class="text-xs text-gray-500 mb-3">Optional. JPG, PNG or WEBP, maximum 4 MB. Stored privately.</p>
            <div class="flex items-center gap-4">
                <div class="w-28 h-28 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                    <img id="hrPhotoImg" alt="Profile photo" class="hidden w-full h-full object-cover">
                    <i id="hrPhotoPlaceholder" class="fas fa-user text-3xl text-gray-300"></i>
                </div>
                <div class="space-y-2 js-section-action">
                    <input type="file" id="hrPhotoInput" accept="image/jpeg,image/png,image/webp" class="hidden">
                    <button type="button" onclick="document.getElementById('hrPhotoInput').click()" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-upload mr-1"></i> Choose photo</button>
                    <button type="button" id="hrPhotoRemove" onclick="hrRemoveImage('photo')" class="hidden px-3 py-2 text-xs font-semibold rounded-lg border border-red-300 text-red-700 hover:bg-red-50"><i class="fas fa-trash mr-1"></i> Remove photo</button>
                </div>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl p-4">
            <h5 class="text-sm font-bold text-gray-900 mb-1">Signature</h5>
            <p class="text-xs text-gray-500 mb-3" id="hrSigHint">
                @if($hrIsSelf)
                    Draw your signature or upload an image (PNG/JPG, maximum 1 MB). Only you can add or change it; HR can remove it.
                @else
                    Only the owner can add or change their signature. HR can remove it if it is wrong.
                @endif
            </p>
            <div class="flex items-center gap-4">
                <div class="w-48 h-24 rounded-xl border border-gray-200 bg-white flex items-center justify-center overflow-hidden flex-shrink-0" style="background-image:linear-gradient(45deg,#f3f4f6 25%,transparent 25%,transparent 75%,#f3f4f6 75%),linear-gradient(45deg,#f3f4f6 25%,transparent 25%,transparent 75%,#f3f4f6 75%);background-size:12px 12px;background-position:0 0,6px 6px;">
                    <img id="hrSigImg" alt="Signature" class="hidden max-w-full max-h-full object-contain">
                    <span id="hrSigPlaceholder" class="text-xs text-gray-400 bg-white/80 px-2 rounded">No signature</span>
                </div>
                <div class="space-y-2 js-section-action">
                    @if($hrIsSelf)
                        <input type="file" id="hrSigInput" accept="image/png,image/jpeg" class="hidden">
                        <button type="button" onclick="hrOpenSigPad()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-red-800 text-white hover:bg-red-900"><i class="fas fa-pen-nib mr-1"></i> Draw signature</button>
                        <button type="button" onclick="document.getElementById('hrSigInput').click()" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-upload mr-1"></i> Upload image</button>
                    @endif
                    <button type="button" id="hrSigRemove" onclick="hrRemoveImage('signature')" class="hidden px-3 py-2 text-xs font-semibold rounded-lg border border-red-300 text-red-700 hover:bg-red-50"><i class="fas fa-trash mr-1"></i> Remove signature</button>
                </div>
            </div>
        </div>
    </div>

    @if($hrType !== 'External')
    {{-- ── Kepegawaian (Internal): hanya HR yang dapat mengubah ── --}}
    <div>
        <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">Employment <span class="ml-1 text-xs font-normal text-gray-500">(maintained by HR)</span></h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Employment status</label>
                <select id="hrStatus" data-field="employment_status" data-hr-group="main" data-hr-only class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                <p class="text-xs text-red-600 mt-1 hidden" data-error-for="employment_status"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Grade</label>
                <select id="hrGrade" data-field="grade_id" data-hr-group="main" data-hr-only class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                <p class="text-xs text-red-600 mt-1 hidden" data-error-for="grade_id"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Probation end date</label>
                <input type="date" id="hrProbation" data-field="probation_end_date" data-hr-group="main" data-hr-only class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-error-for="probation_end_date"></p>
            </div>
            <div id="hrReasonWrap" class="md:col-span-3 hidden">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Reason for status change <span class="font-normal text-gray-400">(optional, recorded in the employee history)</span></label>
                <input type="text" id="hrStatusReason" maxlength="200" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800" placeholder="e.g. Passed probation evaluation">
            </div>
        </div>
    </div>

    @endif

    {{-- ── Engagement konsultan (External saja; hanya HR, tidak pernah untuk diri sendiri; tarif punya izin sendiri) ── --}}
    @if($hrType === 'External' && !$hrIsSelf && $can('employee.section.engagement.view'))
    <div id="hrEngagementCard" class="border border-gray-200 rounded-xl p-4" data-can-edit="{{ $can('employee.section.engagement.update') && !$hrReadonly ? '1' : '0' }}">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h5 class="text-sm font-bold text-gray-900">Engagement <span class="ml-1 text-xs font-normal text-gray-500">(consultant, maintained by HR)</span></h5>
                <p class="text-xs text-gray-500 mt-0.5">Client, assignment and engagement period. The rate is visible only to users with rate permission.</p>
            </div>
            <button type="button" id="hrEngSave" onclick="hrEngSave()" class="js-eng-action px-3 py-2 bg-red-800 text-white text-xs font-semibold rounded-lg hover:bg-red-900"><i class="fas fa-save mr-1"></i> <span id="hrEngSaveText">Save engagement</span></button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Engagement scheme</label>
                <select id="hrEngScheme" data-eng="engagement_scheme" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="engagement_scheme"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Vendor / Partner</label>
                <input type="text" data-eng="vendor_partner" maxlength="150" placeholder="Leave empty if engaged directly" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="vendor_partner"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Client / Principal company</label>
                <input type="text" data-eng="client_company" maxlength="150" placeholder="e.g. Eclectic" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="client_company"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Assignment / Role</label>
                <input type="text" data-eng="assignment_role" maxlength="150" placeholder="e.g. Finance Consultant" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="assignment_role"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Managed by</label>
                <input type="text" data-eng="managed_by" maxlength="150" placeholder="e.g. Head of RPMO" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="managed_by"></p>
            </div>
            <div class="hidden md:block"></div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Start date</label>
                <input type="date" data-eng="start_date" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="start_date"></p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">End date</label>
                <input type="date" data-eng="end_date" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="end_date"></p>
            </div>
            <div class="hidden md:block"></div>
            <div id="hrEngRateWrap" class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4 hidden">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5"><i class="fas fa-lock text-[10px] mr-1 text-amber-600"></i>Rate <span class="font-normal text-gray-400">(sensitive)</span></label>
                    <input type="text" data-eng="rate" inputmode="decimal" placeholder="e.g. 2.700.000,00" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                    <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="rate"></p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Currency</label>
                    <select data-eng="currency" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-800"></select>
                    <p class="text-xs text-red-600 mt-1 hidden" data-eng-error="currency"></p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Catatan HR (semua jenis; tidak tampil bagi pemilik) ── --}}
    @if(!$hrIsSelf)
    <div>
        <h5 class="text-sm font-bold text-gray-900 mb-3 pb-2 border-b border-gray-200">HR notes <span class="ml-1 text-xs font-normal text-gray-500">(internal, not visible to the employee)</span></h5>
        <textarea id="hrNotes" data-field="hr_notes" data-hr-group="main" data-hr-only rows="3" maxlength="2000" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800"></textarea>
        <p class="text-xs text-red-600 mt-1 hidden" data-error-for="hr_notes"></p>
    </div>
    @endif
</div>

{{-- Papan tanda tangan (hanya pemilik). Gambar dengan mouse/jari/stylus; hasilnya PNG transparan. --}}
@if($hrIsSelf)
<div id="hrSigModal" class="hidden fixed inset-0 z-[60] bg-black/50 items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="hrSigTitle">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-5">
        <h3 id="hrSigTitle" class="text-base font-bold text-gray-900">Draw your signature</h3>
        <p class="text-xs text-gray-500 mt-1 mb-3">Use your mouse, finger or stylus and sign inside the box. This is a drawn signature image used on your documents; it is not a certified digital signature.</p>
        <canvas id="hrSigCanvas" class="w-full rounded-lg border-2 border-dashed border-gray-300 bg-white" style="height:200px;touch-action:none;cursor:crosshair"></canvas>
        <div class="flex items-center justify-between mt-4">
            <button type="button" onclick="hrSigClear()" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-eraser mr-1"></i> Clear</button>
            <div class="flex gap-2">
                <button type="button" onclick="hrCloseSigPad()" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" id="hrSigUse" onclick="hrSigSave()" class="px-3 py-2 text-xs font-semibold rounded-lg bg-red-800 text-white hover:bg-red-900">Use this signature</button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    const root = document.getElementById('hrProfileRoot');
    if (!root) { return; }
    const EMP = root.dataset.employeeId;
    const IS_SELF = root.dataset.isSelf === '1';
    const BASE = '/api/employees/' + EMP + '/hr-profile';
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const $ = (id) => document.getElementById(id);
    let state = null;          // data terakhir dari server
    let originalStatus = null; // untuk memunculkan kolom alasan

    const notify = (m, t) => (typeof showNotification === 'function' ? showNotification(m, t) : alert(m));

    async function api(method, url, body, isForm) {
        const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() };
        if (body && !isForm) { headers['Content-Type'] = 'application/json'; }
        const res = await fetch(url, { method, credentials: 'same-origin', headers, body: body ? (isForm ? body : JSON.stringify(body)) : undefined });
        let json = null;
        try { json = await res.json(); } catch (e) { /* bukan JSON */ }
        return { ok: res.ok, status: res.status, json };
    }

    function bust() { return '?v=' + Date.now(); }

    function setImage(kind, has) {
        const img = $(kind === 'photo' ? 'hrPhotoImg' : 'hrSigImg');
        const ph = $(kind === 'photo' ? 'hrPhotoPlaceholder' : 'hrSigPlaceholder');
        const rm = $(kind === 'photo' ? 'hrPhotoRemove' : 'hrSigRemove');
        if (has) {
            img.onerror = () => { img.classList.add('hidden'); ph.classList.remove('hidden'); rm.classList.add('hidden'); };
            img.src = BASE + '/' + kind + bust();
            img.classList.remove('hidden'); ph.classList.add('hidden'); rm.classList.remove('hidden');
        } else {
            img.removeAttribute('src'); img.classList.add('hidden'); ph.classList.remove('hidden'); rm.classList.add('hidden');
        }
        if (kind === 'photo') { updateHeaderPhoto(has); }
    }

    function updateHeaderPhoto(has) {
        const h = document.getElementById('headerPhoto'), i = document.getElementById('headerInitials');
        if (!h || !i) { return; }
        if (has) { h.src = BASE + '/photo' + bust(); h.classList.remove('hidden'); i.classList.add('hidden'); }
        else { h.removeAttribute('src'); h.classList.add('hidden'); i.classList.remove('hidden'); }
    }

    function fillSelect(sel, options, placeholder) {
        if (!sel) { return; }
        sel.innerHTML = '';
        const first = document.createElement('option');
        first.value = ''; first.textContent = placeholder;
        sel.appendChild(first);
        options.forEach(function (o) {
            const op = document.createElement('option');
            op.value = o.value; op.textContent = o.label;
            sel.appendChild(op);
        });
    }

    function populate(d) {
        state = d;
        const f = d.fields || {};
        fillSelect($('hrStatus'), Object.keys(d.status_options || {}).map(k => ({ value: k, label: d.status_options[k] })), 'Not set');
        fillSelect($('hrGrade'), (d.grades || []).map(g => ({ value: String(g.id), label: g.name })), 'Not set');
        document.querySelectorAll('[data-field][data-hr-group]').forEach(function (el) {
            const v = f[el.dataset.field];
            el.value = (v === null || v === undefined) ? '' : String(v);
            // Field kepegawaian & catatan HR: pemilik hanya melihat (penegakan sebenarnya di server)
            if (el.hasAttribute('data-hr-only') && IS_SELF) { el.disabled = true; el.classList.add('bg-gray-50', 'text-gray-500'); }
        });
        originalStatus = f.employment_status || '';
        $('hrReasonWrap')?.classList.add('hidden');
        setImage('photo', !!d.has_photo);
        setImage('signature', !!d.has_signature);
        if (!IS_SELF && !d.has_signature) { $('hrSigRemove')?.classList.add('hidden'); }
    }

    async function load() {
        const r = await api('GET', BASE);
        if (r.ok && r.json && r.json.success) { populate(r.json.data); }
        else { notify((r.json && r.json.message) || 'Could not load the HR profile.', 'error'); }
    }

    function clearErrors() {
        document.querySelectorAll('[data-error-for]').forEach(e => { e.classList.add('hidden'); e.textContent = ''; });
    }
    function showErrors(errors) {
        Object.keys(errors || {}).forEach(function (k) {
            const el = document.querySelector('[data-error-for="' + k + '"]');
            if (el) { el.textContent = errors[k]; el.classList.remove('hidden'); }
        });
    }

    // Simpan satu grup field: 'main' (kepegawaian + catatan HR), 'personal' (golongan darah, ibu kandung — di Basic Data),
    // 'emergency' (kontak darurat — di tab Family). opts.silent = tanpa notifikasi sukses (dipakai tombol Save Basic Data).
    window.hrSaveGroup = async function (group, opts) {
        opts = opts || {};
        const fields = Array.prototype.slice.call(document.querySelectorAll('[data-field][data-hr-group="' + group + '"]'));
        const payload = {};
        fields.forEach(function (el) { if (!el.disabled) { payload[el.dataset.field] = el.value; } });
        if (!Object.keys(payload).length) { return true; }   // tidak ada yang dapat diubah (mis. read-only)
        if (group === 'main' && !IS_SELF && $('hrStatusReason') && $('hrStatus') && $('hrStatus').value !== originalStatus) {
            payload.status_reason = $('hrStatusReason').value;
        }
        fields.forEach(function (el) { const e = document.querySelector('[data-error-for="' + el.dataset.field + '"]'); if (e) { e.classList.add('hidden'); e.textContent = ''; } });
        const btn = $(group === 'main' ? 'hrSaveBtn' : group === 'emergency' ? 'hrEcSaveBtn' : '');
        const btnText = $(group === 'main' ? 'hrSaveBtnText' : group === 'emergency' ? 'hrEcSaveBtnText' : '');
        if (btn) { btn.disabled = true; if (btnText) { btnText.textContent = 'Saving…'; } }
        try {
            const r = await api('POST', BASE, payload);
            if (r.ok && r.json && r.json.success) {
                if (!opts.silent) { notify('Saved successfully.', 'success'); }
                await load();
                return true;
            }
            showErrors(r.json && r.json.errors);
            notify((r.json && r.json.message) || 'Could not save.', 'error');
        } catch (e) { notify('An error occurred while saving.', 'error'); }
        finally { if (btn) { btn.disabled = false; if (btnText) { btnText.textContent = 'Save'; } } }
        return false;
    };
    window.hrSave = function () { return window.hrSaveGroup('main'); };
    // Dipanggil oleh tombol Save di Basic Data (saveCurrentSection) agar golongan darah/ibu kandung ikut tersimpan.
    window.hrSavePersonal = function () { return window.hrSaveGroup('personal', { silent: true }); };

    async function uploadFile(kind, fileOrBlob, name) {
        const fd = new FormData();
        fd.append('file', fileOrBlob, name || 'upload');
        const r = await api('POST', BASE + '/' + kind, fd, true);
        if (r.ok && r.json && r.json.success) { notify(r.json.message, 'success'); await load(); return true; }
        notify((r.json && r.json.message) || 'Upload failed.', 'error');
        return false;
    }

    window.hrRemoveImage = async function (kind) {
        const label = kind === 'photo' ? 'photo' : 'signature';
        const msg = 'Remove this ' + label + '?';
        const ok = typeof showConfirm === 'function' ? await showConfirm(msg, 'Remove ' + label, 'danger') : window.confirm(msg);
        if (!ok) { return; }
        const r = await api('POST', BASE + '/' + kind + '/delete');
        if (r.ok && r.json && r.json.success) { notify(r.json.message, 'success'); await load(); }
        else { notify((r.json && r.json.message) || 'Could not remove the ' + label + '.', 'error'); }
    };

    function bindFileInput(id, kind) {
        const input = $(id);
        if (!input) { return; }
        input.addEventListener('change', async function () {
            const f = input.files && input.files[0];
            input.value = '';
            if (!f) { return; }
            const max = kind === 'photo' ? 4 * 1024 * 1024 : 1024 * 1024;
            if (f.size > max) { notify('The image is too large. Maximum size is ' + (max / 1048576) + ' MB.', 'error'); return; }
            await uploadFile(kind, f, f.name);
        });
    }
    bindFileInput('hrPhotoInput', 'photo');
    bindFileInput('hrSigInput', 'signature');

    // Kolom alasan muncul hanya bila HR mengubah status
    $('hrStatus')?.addEventListener('change', function () {
        if (IS_SELF) { return; }
        $('hrReasonWrap').classList.toggle('hidden', $('hrStatus').value === originalStatus);
    });

    // ───────────── Papan tanda tangan (canvas) ─────────────
    const cv = $('hrSigCanvas');
    if (cv) {
        const ctx = cv.getContext('2d');
        let drawing = false, last = null, hasInk = false, box = null;
        const INK = '#0b1f4d';

        function fit() {
            const dpr = Math.max(1, window.devicePixelRatio || 1);
            const w = cv.clientWidth, h = cv.clientHeight;
            if (!w || !h) { return; }
            cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = INK; ctx.lineWidth = 2.4;
            hasInk = false; box = null;
        }
        const pos = (e) => { const r = cv.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; };
        function grow(p) {
            box = box ? { x1: Math.min(box.x1, p.x), y1: Math.min(box.y1, p.y), x2: Math.max(box.x2, p.x), y2: Math.max(box.y2, p.y) } : { x1: p.x, y1: p.y, x2: p.x, y2: p.y };
        }
        cv.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            try { cv.setPointerCapture(e.pointerId); } catch (x) { /* pointer sintetis atau tidak aktif: abaikan */ }
            drawing = true; last = pos(e); grow(last);
            ctx.beginPath(); ctx.arc(last.x, last.y, 1.1, 0, Math.PI * 2); ctx.fillStyle = INK; ctx.fill(); hasInk = true;
        });
        cv.addEventListener('pointermove', function (e) {
            if (!drawing) { return; }
            e.preventDefault();
            const p = pos(e);
            // kurva halus: titik tengah sebagai kontrol
            ctx.beginPath(); ctx.moveTo(last.x, last.y);
            ctx.quadraticCurveTo(last.x, last.y, (last.x + p.x) / 2, (last.y + p.y) / 2);
            ctx.lineTo(p.x, p.y); ctx.stroke();
            last = p; grow(p); hasInk = true;
        });
        const stop = (e) => { drawing = false; try { cv.releasePointerCapture(e.pointerId); } catch (x) { /* ok */ } };
        cv.addEventListener('pointerup', stop);
        cv.addEventListener('pointercancel', stop);

        window.hrSigClear = function () { ctx.clearRect(0, 0, cv.width, cv.height); hasInk = false; box = null; };
        window.hrOpenSigPad = function () {
            const m = $('hrSigModal'); m.classList.remove('hidden'); m.classList.add('flex');
            requestAnimationFrame(fit);
        };
        window.hrCloseSigPad = function () { const m = $('hrSigModal'); m.classList.add('hidden'); m.classList.remove('flex'); };
        window.hrSigSave = async function () {
            if (!hasInk || !box) { notify('Please draw your signature first.', 'warning'); return; }
            // Potong ke kotak tinta + margin agar tanda tangan tidak mengecil di dokumen
            const dpr = Math.max(1, window.devicePixelRatio || 1), pad = 10;
            const sx = Math.max(0, Math.floor((box.x1 - pad) * dpr)), sy = Math.max(0, Math.floor((box.y1 - pad) * dpr));
            const sw = Math.min(cv.width - sx, Math.ceil((box.x2 - box.x1 + pad * 2) * dpr));
            const sh = Math.min(cv.height - sy, Math.ceil((box.y2 - box.y1 + pad * 2) * dpr));
            const out = document.createElement('canvas');
            out.width = Math.max(1, sw); out.height = Math.max(1, sh);
            out.getContext('2d').drawImage(cv, sx, sy, sw, sh, 0, 0, sw, sh);
            const btn = $('hrSigUse'); btn.disabled = true;
            out.toBlob(async function (blob) {
                try {
                    if (!blob) { notify('Could not create the signature image.', 'error'); return; }
                    if (await uploadFile('signature', blob, 'signature.png')) { hrCloseSigPad(); }
                } finally { btn.disabled = false; }
            }, 'image/png');
        };
        window.addEventListener('resize', function () { if (!$('hrSigModal').classList.contains('hidden') && !hasInk) { fit(); } });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('hrSigModal').classList.contains('hidden')) { hrCloseSigPad(); } });
    }


    // ───────────── Engagement konsultan (HC-D47) ─────────────
    const eng = $('hrEngagementCard');
    if (eng) {
        const EBASE = '/api/employees/' + EMP + '/engagement';
        const canEditBase = eng.dataset.canEdit === '1';
        let canEditRate = false;
        const fieldsOf = () => Array.prototype.slice.call(eng.querySelectorAll('[data-eng]'));
        const fmtRate = (v) => (v === null || v === undefined || v === '') ? '' : Number(v).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        function engErrors(errors) {
            eng.querySelectorAll('[data-eng-error]').forEach(e => { e.classList.add('hidden'); e.textContent = ''; });
            Object.keys(errors || {}).forEach(function (k) {
                const el = eng.querySelector('[data-eng-error="' + k + '"]');
                if (el) { el.textContent = errors[k]; el.classList.remove('hidden'); }
            });
        }

        async function engLoad() {
            const r = await api('GET', EBASE);
            if (!(r.ok && r.json && r.json.success)) { notify((r.json && r.json.message) || 'Could not load the engagement details.', 'error'); return; }
            const d = r.json.data;
            if (!d.applicable) { eng.classList.add('hidden'); return; }
            fillSelect(eng.querySelector('[data-eng="engagement_scheme"]'), Object.keys(d.scheme_options || {}).map(k => ({ value: k, label: d.scheme_options[k] })), 'Not set');
            fillSelect(eng.querySelector('[data-eng="currency"]'), (d.currencies || []).map(c => ({ value: c, label: c })), 'Currency');
            const f = d.fields || {};
            canEditRate = !!d.can_edit_rate && canEditBase;
            $('hrEngRateWrap').classList.toggle('hidden', !d.can_view_rate);
            fieldsOf().forEach(function (el) {
                const key = el.dataset.eng;
                let v = f[key];
                if (key === 'rate') { v = fmtRate(v); }
                el.value = (v === null || v === undefined) ? '' : String(v);
                const isRate = key === 'rate' || key === 'currency';
                el.disabled = isRate ? !canEditRate : !canEditBase;
                if (el.disabled) { el.classList.add('bg-gray-50', 'text-gray-500'); }
            });
            $('hrEngSave').classList.toggle('hidden', !canEditBase);
        }

        window.hrEngSave = async function () {
            engErrors({});
            const payload = {};
            fieldsOf().forEach(function (el) {
                if (el.disabled) { return; }
                if ((el.dataset.eng === 'rate' || el.dataset.eng === 'currency') && !canEditRate) { return; }
                payload[el.dataset.eng] = el.value;
            });
            const btn = $('hrEngSave'); btn.disabled = true; $('hrEngSaveText').textContent = 'Saving…';
            try {
                const r = await api('POST', EBASE, payload);
                if (r.ok && r.json && r.json.success) { notify('Engagement details saved successfully.', 'success'); await engLoad(); }
                else { engErrors(r.json && r.json.errors); notify((r.json && r.json.message) || 'Could not save the engagement details.', 'error'); }
            } catch (e) { notify('An error occurred while saving the engagement details.', 'error'); }
            finally { btn.disabled = false; $('hrEngSaveText').textContent = 'Save engagement'; }
        };

        document.addEventListener('DOMContentLoaded', engLoad);
    }

    document.addEventListener('DOMContentLoaded', load);
})();
</script>

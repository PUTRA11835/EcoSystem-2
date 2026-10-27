@extends('dashboard')
@section('title', 'External Consultants')
@section('page-title', 'External Consultants')
@section('page-subtitle', 'Profiles, engagement schemes, rates and assignments of external consultants — read from the employee master data.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $filterForm = 'consultantFilters';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-green-50 text-green-600', 'amber' => 'bg-amber-50 text-amber-600', 'gray' => 'bg-gray-100 text-gray-500'];
    // Each card filters the list when clicked (the same filters as the form below).
    $cards = [
        ['Total consultants', $summary['total'], 'users', 'blue', []],
        ['Active', $summary['active'], 'user-check', 'green', ['status' => 'active']],
        ['Mandays', $summary['mandays'], 'calendar-day', 'blue', ['scheme' => 'mandays']],
        ['Monthly', $summary['monthly'], 'calendar-days', 'amber', ['scheme' => 'monthly']],
        ['Other / not set', $summary['other'] + $summary['unset'], 'circle-question', 'gray', ['scheme' => 'other_unset']],
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 max-w-3xl">
            Consultants are the <strong>External</strong> employees of the master data. Their profile and engagement are changed in <strong>Employee Data</strong>; this page reads them from there, so there is still one source.
        </p>
        <div class="flex flex-wrap gap-2">
            @if($canDo('general.external.timesheet'))<a href="{{ route('general.external.timesheet.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-clock mr-1"></i> Timesheet &amp; Payment</a>@endif
            @if($canDo('general.external.contracts'))<a href="{{ route('general.external.contracts.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-file-contract mr-1"></i> External Contract</a>@endif
            @if($canDo('general.external.consultants', 'create'))<button type="button" id="addConsultantBtn" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Consultant</button>@else<button type="button" disabled aria-disabled="true" title="Needs the Create permission on External Consultants. Ask an administrator (Control Center → Menu Access)." class="px-4 py-2 bg-gray-100 text-gray-400 text-xs font-semibold rounded-lg border border-gray-200 cursor-not-allowed"><i class="fas fa-lock mr-1"></i> Add Consultant</button>@endif
        </div>
    </div>

    {{-- Summary: compact cards like the dashboards; click one to filter --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach($cards as [$label, $count, $icon, $tone, $query])
            @php
                $active = $query !== [] && collect($query)->every(fn ($v, $k) => ($filters[$k] ?? '') === $v);
                $url = route('general.external.consultants.index', $query);
            @endphp
            <a href="{{ $url }}"
               class="bg-white rounded-xl border shadow-sm p-4 flex items-center gap-3 hover:shadow transition {{ $active ? 'border-indigo-300 ring-2 ring-indigo-200' : 'border-gray-200' }}">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$tone] }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($count) }}</p>
                    <p class="text-[11px] text-gray-500 mt-1">{{ $label }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="{{ $filterForm }}" method="GET" action="{{ route('general.external.consultants.index') }}" data-unsaved-ignore
              class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-5 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="extSearch" value="{{ $filters['search'] }}" autocomplete="off"
                       placeholder="Search name, consultant ID, vendor, client or assignment"
                       class="{{ $input }} pl-9 pr-9" aria-label="Search consultants">
                <button type="button" id="extSearchClear" class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 {{ $filters['search'] === '' ? 'hidden' : '' }}" aria-label="Clear search"><i class="fas fa-xmark text-xs"></i></button>
            </div>
            <div class="md:col-span-3">
                <select name="scheme" class="{{ $input }}" aria-label="Scheme">
                    <option value="">All schemes</option>
                    @foreach($schemes as $value => $label)<option value="{{ $value }}" @selected($filters['scheme'] === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <select name="status" class="{{ $input }}" aria-label="Status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="md:col-span-1 flex justify-end">
                <button type="button" id="extRefresh" class="w-full md:w-auto px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 inline-flex items-center justify-center gap-1.5" title="Refresh the list">
                    <i class="fas fa-rotate" id="extRefreshIcon"></i><span class="md:hidden">Refresh</span>
                </button>
            </div>
        </form>

        <div id="extList" class="transition-opacity" aria-live="polite">
            @include('hr-general.external.consultants.components.list-table')
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById(@json($filterForm));
    const list = document.getElementById('extList');
    const search = document.getElementById('extSearch');
    const clear = document.getElementById('extSearchClear');
    const icon = document.getElementById('extRefreshIcon');
    let timer = null, controller = null;

    function query(extra) {
        const map = new Map();
        new FormData(form).forEach((v, k) => map.set(k, v));
        Object.entries(extra || {}).forEach(([k, v]) => map.set(k, v));
        const q = new URLSearchParams();
        map.forEach((v, k) => { if (String(v) !== '') q.set(k, v); });
        return q.toString();
    }

    async function load(qs, push = true) {
        if (controller) controller.abort();
        controller = new AbortController();
        list.classList.add('opacity-50'); icon.classList.add('fa-spin');
        const url = form.action + (qs ? '?' + qs : '');
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            list.innerHTML = await res.text();
            if (push) history.pushState({ qs }, '', url);
        } catch (e) {
            if (e.name !== 'AbortError') window.showNotification('The list could not be refreshed. Please try again.', 'error');
        } finally {
            list.classList.remove('opacity-50'); icon.classList.remove('fa-spin');
        }
    }

    const reload = () => load(query({ page: '' }));
    search.addEventListener('input', () => { clear.classList.toggle('hidden', search.value === ''); clearTimeout(timer); timer = setTimeout(reload, 300); });
    form.addEventListener('change', e => { if (e.target.matches('select')) reload(); });
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); reload(); });
    clear.addEventListener('click', () => { search.value = ''; clear.classList.add('hidden'); search.focus(); reload(); });
    document.getElementById('extRefresh').addEventListener('click', () => { clearTimeout(timer); load(query()); });
    list.addEventListener('click', e => {
        const a = e.target.closest('a[href*="page="]');
        if (!a || e.metaKey || e.ctrlKey) return;
        e.preventDefault();
        load(new URL(a.href).searchParams.toString());
    });
    window.addEventListener('popstate', () => load(location.search.replace(/^\?/, ''), false));
})();
</script>
@endpush

@if($canDo('general.external.consultants', 'create'))
{{-- Add Consultant: membuat akun konsultan (Employee Group = EXTERNAL), lalu membuka halaman edit untuk melengkapi datanya.
     ECI diketik manual (tidak dibuat otomatis): panel urutan di bawah isian ECI menunjukkan usulan berikutnya, ID terakhir, nomor yang kosong,
     dan memeriksa langsung apakah ID/Nick Name sudah dipakai. --}}
<div id="addConsultantModal" class="hidden fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="acTitle">
    <form id="addConsultantForm" class="bg-white rounded-xl shadow-xl w-full max-w-xl my-8" autocomplete="off" novalidate>
        <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
            <div><h3 class="text-base font-bold text-gray-900" id="acTitle">Add consultant</h3>
                <p class="text-xs text-gray-500">Creates the consultant account. You complete the profile, engagement and documents on the next page.</p></div>
            <button type="button" class="ac-close text-gray-400 hover:text-gray-700 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            @php $lbl = 'block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1'; @endphp

            <div class="sm:col-span-2">
                <label class="{{ $lbl }}" for="acEci">Consultant ID (ECI) *</label>
                <div class="flex gap-2">
                    <input id="acEci" name="eci" required maxlength="50" autocomplete="off" spellcheck="false" placeholder="e.g. E26060" class="{{ $input }} flex-1 font-mono uppercase" aria-describedby="acEciStatus">
                    <button type="button" id="acUseNext" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 whitespace-nowrap" disabled>Use next</button>
                </div>
                <p id="acEciStatus" class="text-[11px] mt-1 min-h-[16px]" aria-live="polite"></p>
                <div id="acSeq" class="mt-2 rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-[11px] text-gray-600 space-y-1.5">
                    <p class="font-semibold text-gray-700"><i class="fas fa-list-ol mr-1 text-gray-400"></i> ID sequence <span class="font-normal text-gray-400">— IDs are typed by hand, so check the numbers here first</span></p>
                    <p id="acSeqBody" class="text-gray-400">Loading the sequence…</p>
                </div>
            </div>

            <div><label class="{{ $lbl }}" for="acEmail">Work email *</label><input id="acEmail" name="email_work" type="email" required maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}" for="acFirst">First name *</label><input id="acFirst" name="first_name" required maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}" for="acLast">Last name</label><input id="acLast" name="last_name" maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}" for="acNick">Nick name *</label><input id="acNick" name="nick_name" required maxlength="100" class="{{ $input }}"><p id="acNickStatus" class="text-[11px] mt-1 min-h-[16px]" aria-live="polite"></p></div>
            <div><label class="{{ $lbl }}" for="acPhone">Mobile phone</label><input id="acPhone" name="cell_phone" maxlength="50" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}" for="acTitle2">Title</label><select id="acTitle2" name="title" class="{{ $input }}"><option value="">Select title</option><option value="Mr.">Mr.</option><option value="Mrs.">Mrs.</option><option value="Ms.">Ms.</option><option value="Dr.">Dr.</option><option value="Prof.">Prof.</option></select></div>
            <div><label class="{{ $lbl }}" for="acGender">Gender</label><select id="acGender" name="gender" class="{{ $input }}"><option value="">Select gender</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            <div><label class="{{ $lbl }}" for="acReligion">Religion</label><select id="acReligion" name="religion" class="{{ $input }}"><option value="">Select religion</option><option value="Islam">Islam</option><option value="Christian">Christian</option><option value="Catholic">Catholic</option><option value="Hindu">Hindu</option><option value="Buddhist">Buddhist</option><option value="Konghucu">Konghucu</option></select></div>
            <div><label class="{{ $lbl }}" for="acMarital">Marital status</label><select id="acMarital" name="marital_status" class="{{ $input }}"><option value="">Select marital status</option><option value="Single">Single</option><option value="Married">Married</option><option value="Divorced">Divorced</option><option value="Widow/Widower">Widow/Widower</option></select></div>
            <div><label class="{{ $lbl }}" for="acBirthDate">Birth date</label><input id="acBirthDate" name="birth_date" type="date" max="{{ now()->toDateString() }}" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}" for="acBirthPlace">Birth place</label><input id="acBirthPlace" name="birth_place" maxlength="100" class="{{ $input }}"></div>
            <p class="sm:col-span-2 text-[11px] text-gray-400 -mt-2">These fields are the same as for internal employees. They are optional here; you can also fill them in on the next page.</p>

            <div>
                <label class="{{ $lbl }}" for="acPass">Password *</label>
                <div class="relative"><input id="acPass" name="password" type="password" required minlength="6" autocomplete="new-password" class="{{ $input }} pr-14"><button type="button" data-ac-eye="acPass" class="absolute right-2 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-gray-500 hover:text-gray-800">Show</button></div>
                <p class="text-[11px] text-gray-400 mt-1">At least 6 characters.</p>
            </div>
            <div>
                <label class="{{ $lbl }}" for="acPass2">Confirm password *</label>
                <div class="relative"><input id="acPass2" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password" class="{{ $input }} pr-14"><button type="button" data-ac-eye="acPass2" class="absolute right-2 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-gray-500 hover:text-gray-800">Show</button></div>
                <p id="acMatch" class="text-[11px] mt-1 min-h-[16px]" aria-live="polite"></p>
            </div>

            <div id="acError" class="sm:col-span-2 hidden text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" role="alert"></div>
        </div>
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <button type="button" class="ac-close px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-white">Cancel</button>
            <button type="submit" id="acSave" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-check mr-2"></i>Create consultant</button>
        </div>
    </form>
</div>
<script>
(function () {
    var modal = document.getElementById('addConsultantModal'), form = document.getElementById('addConsultantForm');
    if (!modal) return;
    var $ = function (id) { return document.getElementById(id); };
    var seqUrl = @json(route('general.external.consultants.eci-sequence'));
    var seq = null, eciState = null, eciChecked = '', nickTaken = false, timer = null, reqId = 0;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var say = function (el, text, tone) { el.textContent = text; el.className = el.className.replace(/\btext-(red|green|amber|gray)-\d+\b/g, '') + ' ' + ({ bad: 'text-red-600', ok: 'text-green-600', warn: 'text-amber-600' }[tone] || 'text-gray-500'); };

    function renderSeq() {
        if (!seq) { $('acSeqBody').textContent = 'The sequence could not be loaded. You can still type an ID; the server rejects duplicates.'; return; }
        var chips = function (items, cls) { return items.map(function (x) { return '<button type="button" data-ac-pick="' + esc(x.eci || x) + '" class="inline-block px-1.5 py-0.5 mr-1 mb-1 rounded border ' + cls + ' font-mono">' + esc(x.eci || x) + '</button>'; }).join(''); };
        $('acSeqBody').innerHTML =
            '<span class="block"><span class="text-gray-500">Next free ID:</span> ' + chips([seq.next], 'border-green-300 bg-green-50 text-green-800') + '<span class="text-gray-400">(' + esc(seq.format) + ' = E + year + 3-digit number)</span></span>' +
            '<span class="block"><span class="text-gray-500">Latest used:</span> ' + (seq.recent.length ? seq.recent.map(function (r) { return '<span class="inline-block mr-2"><span class="font-mono text-gray-800">' + esc(r.eci) + '</span>' + (r.name ? ' <span class="text-gray-400">' + esc(r.name) + '</span>' : '') + '</span>'; }).join('') : '<span class="text-gray-400">none yet this year</span>') + '</span>' +
            (seq.gaps.length ? '<span class="block"><span class="text-gray-500">Unused numbers in between:</span> ' + chips(seq.gaps, 'border-gray-300 bg-white text-gray-700') + '</span>' : '');
        $('acUseNext').disabled = false; $('acUseNext').textContent = 'Use ' + seq.next;
    }

    function load(eci, nick) {
        var id = ++reqId, qs = new URLSearchParams();
        if (eci) qs.set('eci', eci); if (nick) qs.set('nick', nick);
        return fetch(seqUrl + (qs.toString() ? '?' + qs : ''), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); }).then(function (j) { return id === reqId ? j : null; });
    }

    function checkEci() {
        var v = $('acEci').value.trim();
        if (v === '') { eciState = null; say($('acEciStatus'), '', ''); return; }
        say($('acEciStatus'), 'Checking…', '');
        load(v, $('acNick').value.trim()).then(function (j) {
            if (!j) return;
            seq = j.success ? j : seq; eciState = j; eciChecked = v;
            if (j.eci_taken) say($('acEciStatus'), '✗ ' + v + ' is already used' + (j.eci_used_by ? ' by ' + j.eci_used_by : '') + '. Pick another — the next free one is ' + j.next + '.', 'bad');
            else if (j.eci_format_ok === false) say($('acEciStatus'), '✓ Available, but the usual format is ' + j.format + ' (for example ' + j.next + ').', 'warn');
            else say($('acEciStatus'), '✓ ' + v + ' is available.', 'ok');
            renderSeq();
        }).catch(function () { say($('acEciStatus'), 'Could not check the ID now; the server still rejects duplicates.', 'warn'); });
    }
    function checkNick() {
        var v = $('acNick').value.trim();
        if (v === '') { nickTaken = false; say($('acNickStatus'), '', ''); return; }
        load('', v).then(function (j) {
            if (!j) return;
            nickTaken = !!j.nick_taken;
            say($('acNickStatus'), nickTaken ? '✗ This nick name is already taken; choose another (it must be unique).' : '', 'bad');
        }).catch(function () {});
    }
    function matchHint() {
        var a = $('acPass').value, b = $('acPass2').value;
        if (b === '') { say($('acMatch'), '', ''); return true; }
        if (a === b) { say($('acMatch'), '✓ Passwords match.', 'ok'); return true; }
        var extra = a.length !== b.length ? ' (' + a.length + ' vs ' + b.length + ' characters — check for a stray space)' : ' (same length, a character differs)';
        say($('acMatch'), '✗ Passwords do not match' + extra + '.', 'bad');
        return false;
    }

    var open = function () {
        form.reset(); $('acError').classList.add('hidden'); eciState = null; nickTaken = false;
        ['acEciStatus', 'acNickStatus', 'acMatch'].forEach(function (id) { $(id).textContent = ''; });
        $('acSeqBody').textContent = 'Loading the sequence…'; $('acUseNext').disabled = true;
        modal.classList.remove('hidden'); modal.classList.add('flex'); $('acEci').focus();
        load('', '').then(function (j) { seq = j && j.success ? j : null; renderSeq(); }).catch(function () { seq = null; renderSeq(); });
    };
    var close = function () { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    $('addConsultantBtn').addEventListener('click', open);
    modal.querySelectorAll('.ac-close').forEach(function (b) { b.addEventListener('click', close); });
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    $('acEci').addEventListener('input', function () { this.value = this.value.toUpperCase(); clearTimeout(timer); timer = setTimeout(checkEci, 350); });
    $('acNick').addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(checkNick, 400); });
    $('acUseNext').addEventListener('click', function () { if (seq) { $('acEci').value = seq.next; checkEci(); } });
    $('acSeqBody').addEventListener('click', function (e) { var b = e.target.closest('[data-ac-pick]'); if (b) { $('acEci').value = b.dataset.acPick; checkEci(); } });
    $('acPass').addEventListener('input', matchHint); $('acPass2').addEventListener('input', matchHint);
    modal.querySelectorAll('[data-ac-eye]').forEach(function (b) { b.addEventListener('click', function () { var f = $(b.dataset.acEye), show = f.type === 'password'; f.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; }); });

    function fail(lines) {
        var err = $('acError'); err.innerHTML = lines.length > 1 ? '<ul class="list-disc pl-4 space-y-0.5">' + lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>' : esc(lines[0]);
        err.classList.remove('hidden'); $('acSave').disabled = false;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = $('acSave'), err = $('acError'); err.classList.add('hidden');
        // pemeriksaan di layar: semua kekurangan disebut sekaligus
        var problems = [];
        if (!$('acEci').value.trim()) problems.push('Consultant ID (ECI) is required.');
        if (eciState && eciState.eci_taken && eciChecked === $('acEci').value.trim()) problems.push('Consultant ID ' + eciChecked + ' is already used — pick another (next free: ' + (seq ? seq.next : '…') + ').');
        if (!$('acEmail').value.trim()) problems.push('Work email is required (the activation link is sent there).');
        if (!$('acFirst').value.trim()) problems.push('First name is required.');
        if (!$('acNick').value.trim()) problems.push('Nick name is required.');
        if (nickTaken) problems.push('This nick name is already taken; choose another.');
        if ($('acPass').value.length < 6) problems.push('The password needs at least 6 characters.');
        if (!matchHint() || $('acPass2').value === '') problems.push('The password confirmation does not match the password.');
        if (problems.length) { fail(problems); return; }

        btn.disabled = true;
        // Rute web memeriksa token CSRF. Token dikirim di header DAN di badan (_token) — salah satunya cukup; header saja bisa hilang di
        // proksi/peladen tertentu. Bila tetap ditolak (419), ambil token segar dan coba SEKALI lagi sebelum menyerah dengan pesan yang jelas.
        var meta = document.querySelector('meta[name="csrf-token"]');
        var send = function (token) {
            var payload = Object.fromEntries(new FormData(form).entries()); payload._token = token;
            return fetch(@json(route('general.external.consultants.store')), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload)
            });
        };
        send((meta && meta.getAttribute('content')) || '').then(function (r) {
            if (r.status !== 419) return r;
            return fetch(@json(route('general.external.consultants.csrf')), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (t) { return t.ok ? t.json() : Promise.reject(); })
                .then(function (t) { if (meta) meta.setAttribute('content', t.token); return send(t.token); });
        }).then(function (r) {
            if (r.status === 419) { return { ok: false, j: { message: 'Your session has expired. Reload the page and sign in again if asked; what you typed is not saved.' } }; }
            if (r.status === 403) { return { ok: false, j: { message: 'You do not have permission to add consultants (Create on External Consultants).' } }; }
            return r.json().then(function (j) { return { ok: r.ok, j: j }; }, function () { return { ok: false, j: { message: 'The server answered with an unexpected page (code ' + r.status + '). Reload and try again; if it repeats, tell the administrator.' } }; });
        })
          .then(function (res) {
              if (res.ok && res.j.success) {
                  if (window.showNotification) showNotification('Consultant created. Complete the profile on the next page.', 'success');
                  window.location.href = @json(url('/general/external/consultants')) + '/' + res.j.data.employee_id + '/edit';
              } else {
                  // SEMUA galat dari server ditampilkan (bukan hanya yang pertama)
                  var lines = [];
                  if (res.j.errors) Object.keys(res.j.errors).forEach(function (k) { (res.j.errors[k] || []).forEach(function (m) { lines.push(m); }); });
                  fail(lines.length ? lines : [res.j.message || 'Could not create the consultant.']);
              }
          }).catch(function () { fail(['Could not reach the server (network problem). Check your connection and try again.']); });
    });
})();
</script>
@endif
@endsection

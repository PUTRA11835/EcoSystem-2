@extends('dashboard')

@section('title', isset($isOwnProfile) && $isOwnProfile ? 'My Profile' : 'Employee Detail')
@section('page-title', isset($isOwnProfile) && $isOwnProfile ? 'My Profile' : 'Employee Detail - ' . (($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '') ?: 'N/A'))

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- ── Loading state ────────────────────────────────────────────────────────
     Halaman ini dirender server-side, TAPI isi form (Basic Data) baru diisi
     setelah fetch /api/employees/{id}/basic-data selesai. Tanpa placeholder,
     user melihat form kosong dulu lalu tiba-tiba terisi — terbaca seperti
     "data hilang". Skeleton di bawah tampil lebih dulu, konten asli baru
     dimunculkan setelah data awal masuk (lihat revealProfilePage()). --}}
<style>
    #profileContent.is-loading { display: none; }
    #profileContent.is-revealed { animation: profileFadeIn .25s ease-out both; }
    @keyframes profileFadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

    /* Overlay saat pindah tab yang datanya diambil via AJAX (mis. Address).
       Warna abu netral supaya aman di light maupun dark mode. */
    .section-loading { position: relative; min-height: 160px; }
    .section-loading > .section-loading-veil {
        position: absolute; inset: 0; z-index: 20;
        display: flex; align-items: flex-start; justify-content: center;
        padding-top: 3rem;
        background: rgba(127, 127, 127, .18);
        border-radius: .5rem;
    }
    .section-spinner {
        width: 2rem; height: 2rem; border-radius: 9999px;
        border: 3px solid rgba(127, 127, 127, .35);
        border-top-color: rgb(var(--primary-rgb, 153 27 27));
        animation: sectionSpin .7s linear infinite;
    }
    @keyframes sectionSpin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        #profileContent.is-revealed { animation: none; }
        .section-spinner { animation-duration: 2s; }
        #profileSkeleton .animate-pulse { animation: none; }
    }
</style>
<noscript>
    <style>#profileSkeleton { display: none !important; } #profileContent.is-loading { display: block !important; }</style>
</noscript>

<div id="profileSkeleton" class="space-y-6" aria-hidden="true">
    <div class="h-10 w-40 rounded-lg bg-gray-200 animate-pulse"></div>

    {{-- Kartu header --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6">
            <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-gray-200 animate-pulse flex-shrink-0"></div>
            <div class="flex-1 w-full min-w-0 space-y-4">
                <div class="space-y-2">
                    <div class="h-8 w-64 max-w-full rounded bg-gray-200 animate-pulse"></div>
                    <div class="h-5 w-40 max-w-full rounded bg-gray-200 animate-pulse"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @for($i = 0; $i < 6; $i++)
                    <div class="space-y-2">
                        <div class="h-3 w-24 rounded bg-gray-200 animate-pulse"></div>
                        <div class="h-4 w-36 max-w-full rounded bg-gray-200 animate-pulse"></div>
                    </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs + form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200 px-6 py-4 flex gap-6 overflow-hidden">
            @for($i = 0; $i < 7; $i++)
            <div class="h-4 w-20 flex-shrink-0 rounded bg-gray-200 animate-pulse"></div>
            @endfor
        </div>
        <div class="p-6 space-y-6">
            <div class="h-5 w-48 rounded bg-gray-200 animate-pulse"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @for($i = 0; $i < 12; $i++)
                <div class="space-y-2">
                    <div class="h-3 w-24 rounded bg-gray-200 animate-pulse"></div>
                    <div class="h-10 w-full rounded-lg bg-gray-200 animate-pulse"></div>
                </div>
                @endfor
            </div>
        </div>
    </div>

    <p class="sr-only" role="status" aria-live="polite">Memuat data profil…</p>
</div>

<div id="profileContent" class="space-y-6 is-loading">
    <!-- Header dengan tombol back -->
    <div class="flex items-center justify-between">
        @if(isset($isOwnProfile) && $isOwnProfile)
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to Dashboard
            </a>
        @else
            <a href="{{ route('master.employee.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to List
            </a>
        @endif
    </div>

    <!-- Employee Profile Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 text-center sm:text-left">
            <img id="headerPhoto" alt="" class="hidden w-24 h-24 sm:w-32 sm:h-32 rounded-full object-cover flex-shrink-0 border border-gray-200">
            <div id="headerInitials" class="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-gradient-to-br from-red-800 to-red-950 text-white flex items-center justify-center font-bold text-3xl sm:text-4xl flex-shrink-0">
                {{ strtoupper(substr(($employee->first_name ?? 'N'), 0, 1) . substr(($employee->last_name ?? 'A'), 0, 1)) }}
            </div>
            <div class="flex-1 w-full min-w-0">
                <div class="flex flex-col sm:flex-row items-center sm:items-start sm:justify-between gap-2 sm:gap-4 mb-4">
                    <div class="min-w-0">
                        <h1 id="headerFullName" class="text-2xl sm:text-3xl font-bold text-gray-900">{{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'N/A' }}</h1>
                        <p id="headerPosition" class="text-lg text-gray-600 mt-1">{{ $employee->position ?? 'N/A' }}</p>
                    </div>
                    <div class="gap-2 flex flex-wrap items-center justify-center sm:justify-start">
                        @php
                            $statusClass = 'bg-gray-100 text-gray-800';
                            $statusLabel = 'Unknown';

                            if (isset($employee->deletion_flag) && $employee->deletion_flag) {
                                $statusClass = 'bg-red-100 text-red-800';
                                $statusLabel = 'Flagged for Deletion';
                            } elseif (isset($employee->block) && $employee->block) {
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                $statusLabel = 'Blocked';
                            } elseif (isset($employee->is_active) && $employee->is_active) {
                                $statusClass = 'bg-green-100 text-green-800';
                                $statusLabel = 'Active';
                            } else {
                                $statusClass = 'bg-gray-100 text-gray-800';
                                $statusLabel = 'Inactive';
                            }
                        @endphp
                        <span id="headerStatusBadge" class="inline-block px-4 py-2 text-sm font-semibold rounded-full {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                        @php $empType = $employee->employee_type ?: 'Internal'; @endphp
                        <span id="headerTypeBadge" class="inline-block px-4 py-2 text-sm font-semibold rounded-full {{ $empType === 'External' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $empType }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500">Employee ID (ECI)</p>
                        <p id="headerEci" class="font-semibold text-gray-900">{{ $employee->eci ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Email (Work)</p>
                        <p id="headerEmail" class="font-semibold text-gray-900">{{ $employee->email_work ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Phone</p>
                        <p id="headerPhone" class="font-semibold text-gray-900">{{ $employee->cell_phone ?? $employee->telephone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Department</p>
                        <p id="headerDepartment" class="font-semibold text-gray-900">{{ $employee->department ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Division</p>
                        <p id="headerDivision" class="font-semibold text-gray-900">{{ $employee->division ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Since Date</p>
                        <p id="headerSinceDate" class="font-semibold text-gray-900">{{ $employee->since_date ? \Carbon\Carbon::parse($employee->since_date)->format('d M Y') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        // Dipakai dua halaman: /profile (my-profile.section.*) dan Master >
        // Employee > detail (employee.section.*). Controller masing-masing yang
        // memutuskan slug mana yang dipakai; view cukup memakai hasilnya.
        // Jangan kembalikan ke pola "$isOwn ? ... : []" — itu membuat halaman
        // Master selalu editable penuh berapa pun izin yang dicentang.
        $hidden   = $profileSectionHidden   ?? [];
        $ro       = $profileSectionReadonly ?? [];
        $sec      = ['employee' => $employee, 'employeeId' => $employee->id];

        // Sections config: key => [tab-id, label, partial]
        $allSections = [
            'basic_data'     => ['basic-data',     'Basic Data',    'basicdata'],
            'address'        => ['address',         'Address',       'address'],
            'identification' => ['identification',  'Identification','identification'],
            'family'         => ['family',          'Family',        'family'],
            'education'      => ['education',       'Education',     'education'],
            'qualification'  => ['qualification',   'Qualification', 'qualification'],
            'contract'       => ['contract',        'Contract',      'contract'],
            'bank'           => ['bank',            'Bank Account',  'bank'],
            'payment'        => ['payment',         'Basic Payment', 'payment'],
            'attachment'     => ['attachment',      'Attachment',    'attachment'],
        ];
        $visibleSections = array_filter($allSections, fn($k) => !($hidden[$k] ?? false), ARRAY_FILTER_USE_KEY);
        // HC-D54: fragmen data HR yang diletakkan di tab lain (golongan darah/ibu kandung di Basic Data, kontak darurat di Family).
        // Hanya untuk yang punya izin seksi hr_profile dan karyawan Internal (form konsultan ESH tidak memilikinya).
        $hrShow = !($hidden['hr_profile'] ?? true) && ((($employee->employee_type ?? null) ?: 'Internal') !== 'External');
        $hrFrag = ['hrPersonal' => $hrShow, 'hrEmergency' => $hrShow, 'hrRo' => (bool) ($ro['hr_profile'] ?? false)];
        $firstKey = array_key_first($visibleSections);
    @endphp

    @if(!empty($profileLocked))
        <div class="bg-gray-50 border border-gray-300 rounded-xl px-5 py-3 mb-5 flex items-start gap-3 text-sm text-gray-700">
            <i class="fas fa-lock mt-0.5 text-gray-500"></i>
            <p>
                @if(isset($isOwnProfile) && $isOwnProfile)
                    <span class="font-semibold">Your profile is verified and locked by HR.</span> Basic Data, Address, Identification, Bank Account and HR Profile are view-only. Please contact HR to request a change.
                @else
                    <span class="font-semibold">This profile is locked.</span> The employee cannot change it; as HR you can. Unlock it from the Onboarding page when changes are needed, then lock it again.
                @endif
            </p>
        </div>
    @endif

    {{-- HC-D14/D27/D50 — Kartu "Data readiness" ala ESH (HANYA My Profile; $onboarding tidak dikirim halaman Master).
         Dikelompokkan (Personal, Payroll, BPJS, Contract); tiap butir yang kurang adalah TOMBOL yang membuka tab dan
         menyorot kolom isiannya. Butir yang tidak dapat diisi pemilik (seksi View Only / dikelola HR) tampil sebagai
         penanda "HR". Dirender sepenuhnya di sisi klien dari data awal (data-initial) dan disegarkan setelah simpan. --}}
    @php
        $onb = $onboarding ?? null;
        $onbSectionNames = [
            'basic-data' => 'Basic Data', 'address' => 'Address', 'identification' => 'Identification',
            'bank' => 'Bank Account', 'contract' => 'Contract',
        ];
        // Seksi yang tampak DAN tidak read-only bagi pemilik.
        $onbEditableSections = [];
        foreach (array_keys($onbSectionNames) as $onbSec) {
            $onbSecKey = str_replace('-', '_', $onbSec);
            if (isset($visibleSections[$onbSecKey]) && !($ro[$onbSecKey] ?? false)) {
                $onbEditableSections[] = $onbSec;
            }
        }
        $onbPayload = $onb ? [
            'done' => $onb['done'], 'total' => $onb['total'], 'percent' => $onb['percent'], 'status' => $onb['status'],
            'groups' => array_map(fn ($g) => ['label' => $g['label'], 'done' => $g['done'], 'total' => $g['total']], $onb['groups']),
            'items' => array_map(fn ($i) => [
                'key' => $i['key'], 'group' => $i['group'], 'label' => $i['label'], 'section' => $i['section'],
                'hint' => $i['hint'] ?? null, 'field' => $i['field'] ?? null, 'prefill' => $i['prefill'] ?? null, 'hr_only' => (bool) ($i['hr_only'] ?? false), 'done' => (bool) $i['done'],
            ], $onb['items']),
        ] : null;
        // Peta butir → tab + kolom, untuk tautan ?section=&field=<key> dari halaman Onboarding (HR).
        $onbFieldMap = [];
        foreach ((array) config('hc_onboarding.items', []) as $cfgItem) {
            $onbFieldMap[$cfgItem['key']] = ['section' => $cfgItem['section'] ?? null, 'field' => $cfgItem['field'] ?? null, 'prefill' => $cfgItem['prefill'] ?? null];
        }
    @endphp
    @if($onb)
    <div id="onboardingBanner" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-5"
         data-url="{{ route('profile.onboarding-progress') }}"
         data-editable='@json($onbEditableSections)'
         data-names='@json($onbSectionNames)'
         data-initial='@json($onbPayload)'>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-gray-900">Data readiness</h3>
                <p id="onbSubtitle" class="text-sm mt-0.5 text-gray-600">&nbsp;</p>
            </div>
            <div class="md:w-72">
                <div class="flex items-end justify-between">
                    <span id="onbCount" class="text-xs text-gray-500">&nbsp;</span>
                    <span id="onbPercent" class="text-2xl font-bold text-gray-900">{{ $onb['percent'] }}%</span>
                </div>
                <div class="mt-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div id="onbBar" class="h-2 rounded-full primary-solid" style="width: {{ $onb['percent'] }}%"></div>
                </div>
            </div>
        </div>
        <div id="onbGroups" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4"></div>
    </div>
    @endif

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px overflow-x-auto" aria-label="Profile sections">
                @foreach($visibleSections as $key => [$tabId, $label, $partial])
                <button onclick="switchSection('{{ $tabId }}')" data-section="{{ $tabId }}"
                    class="section-tab px-6 py-4 text-sm font-semibold border-b-2 whitespace-nowrap
                        {{ $key === $firstKey ? 'border-red-800 text-red-800 bg-red-50/60' : 'border-transparent text-gray-600 hover:text-red-800 hover:bg-gray-50 hover:border-gray-300' }}">
                    {{ $label }}
                </button>
                @endforeach
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            @forelse($visibleSections as $key => [$tabId, $label, $partial])
            <div id="section-{{ $tabId }}" class="section-content {{ $key !== $firstKey ? 'hidden' : '' }}">
                @include("master.employee.sections.{$partial}", $sec + $hrFrag + ['isReadonly' => (bool)($ro[$key] ?? false)])
                {{-- HC-D51: foto, tanda tangan, status kepegawaian, data darurat, (konsultan) engagement ditampilkan di DALAM tab
                     Basic Data — bukan tab sendiri. Hanya untuk yang punya izin seksi `hr_profile` (data tersimpan di tabel terpisah). --}}
                @if($key === 'basic_data' && !($hidden['hr_profile'] ?? true))
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        @include('master.employee.sections.hrprofile', $sec + ['isReadonly' => (bool)($ro['hr_profile'] ?? false)])
                    </div>
                @endif
            </div>
            @empty
            <div class="py-12 text-center">
                <i class="fas fa-lock text-3xl text-gray-300 mb-3"></i>
                <p class="text-sm font-semibold text-gray-700">Tidak ada section yang bisa ditampilkan</p>
                <p class="text-xs text-gray-500 mt-1">
                    Role Anda belum diberi izin section mana pun pada menu ini.
                    Hubungi administrator untuk mencentang section yang diperlukan di Control Center &rarr; Menu Access.
                </p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Dibutuhkan di kedua halaman (profile & Master detail), bukan hanya profile. --}}
<style>
.profile-readonly { position: relative; }
.profile-readonly::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 10;
    cursor: not-allowed;
    border-radius: 0.5rem;
}
.profile-readonly input,
.profile-readonly textarea,
.profile-readonly select {
    background: #f9fafb !important;
    color: #6b7280 !important;
    border-color: #e5e7eb !important;
    pointer-events: none !important;
    cursor: not-allowed !important;
}
.profile-readonly .custom-dd-btn { pointer-events: none !important; cursor: not-allowed !important; }
/* Dropdown native yang di-enhance select-enhance.js (mis. alamat cascading). */
.profile-readonly .se-btn,
.profile-readonly .se-wrap { pointer-events: none !important; cursor: not-allowed !important; }
.profile-readonly .se-btn { background: #f9fafb !important; color: #6b7280 !important; border-color: #e5e7eb !important; }
.profile-readonly .js-section-action { display: none !important; }

/* Text-only action buttons in every section (New / Save / Copy / Delete …): clear keyboard focus + disabled look. */
.js-section-action button:focus-visible, button.js-section-action:focus-visible,
#section-basic-data button[onclick*="save"]:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(153, 27, 27, .25); }
.js-section-action button:disabled, button.js-section-action:disabled { opacity: .6; cursor: not-allowed; }
/* Fields: a visible focus ring so the user always sees where they are typing. */
#profileContent input:focus, #profileContent select:focus, #profileContent textarea:focus { border-color: #991b1b; }
</style>

<script>
    const employeeId = {{ $employee->id }};
    // Tab pertama belum tentu 'basic-data': section tanpa izin .view tidak
    // dirender sama sekali (lihat $visibleSections di atas).
    let currentSection = @json($firstKey ? $visibleSections[$firstKey][0] : 'basic-data');

    // Switch between sections/tabs
    function switchSection(sectionName) {
        currentSection = sectionName;

        // Hide all sections
        document.querySelectorAll('.section-content').forEach(section => {
            section.classList.add('hidden');
        });

        // Remove active from all tabs
        document.querySelectorAll('.section-tab').forEach(tab => {
            tab.classList.remove('border-red-800', 'text-red-800', 'bg-red-50/60');
            tab.classList.add('border-transparent', 'text-gray-600');
        });

        // Show selected section
        const selectedSection = document.getElementById('section-' + sectionName);
        if (selectedSection) {
            selectedSection.classList.remove('hidden');
        }

        // Add active to selected tab
        const selectedTab = document.querySelector(`[data-section="${sectionName}"]`);
        if (selectedTab) {
            selectedTab.classList.add('border-red-800', 'text-red-800', 'bg-red-50/60');
            selectedTab.classList.remove('border-transparent', 'text-gray-600');
            selectedTab.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }

        // Load data for specific sections
        loadSectionData(sectionName);
    }

    // Overlay spinner selama section menunggu datanya sendiri (Basic Data &
    // Address diisi via AJAX, section lain sudah lengkap dari server).
    async function withSectionLoading(sectionName, task) {
        const host = document.getElementById('section-' + sectionName);
        if (!host) return task();

        const veil = document.createElement('div');
        veil.className = 'section-loading-veil';
        veil.setAttribute('role', 'status');
        veil.setAttribute('aria-live', 'polite');
        veil.innerHTML = '<div class="section-spinner"></div><span class="sr-only">Memuat data…</span>';
        host.classList.add('section-loading');
        host.appendChild(veil);

        try {
            return await task();
        } finally {
            veil.remove();
            host.classList.remove('section-loading');
        }
    }

    // Load data based on active section
    function loadSectionData(sectionName) {
        switch(sectionName) {
            case 'basic-data':
                if (typeof loadEmployeeBasicData === 'function') {
                    return withSectionLoading(sectionName, () => loadEmployeeBasicData(employeeId));
                }
                break;
            case 'address':
                if (typeof loadAddresses === 'function') {
                    return withSectionLoading(sectionName, () => loadAddresses(employeeId));
                }
                break;
        }
    }

    // Save current section
    function saveCurrentSection() {
        switch(currentSection) {
            case 'basic-data':
                saveEmployeeBasicData(employeeId);
                // HC-D54: golongan darah + ibu kandung (tabel profil HR) ikut tersimpan bersama Basic Data.
                if (typeof hrSavePersonal === 'function') { hrSavePersonal(); }
                break;
            case 'address':
                if (typeof saveAddresses === 'function') {
                    saveAddresses();
                }
                break;
            default:
                showNotification('Save function not implemented for this section', 'info');
        }
    }

    // Refresh the header card from server without full page reload
    window.refreshHeader = async function(id) {
        try {
            const res = await fetch(`/api/employees/${id}/header`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const result = await res.json();
            if (!result.success) return;
            const d = result.data;
            document.getElementById('headerInitials').textContent    = d.initials     || 'NA';
            document.getElementById('headerFullName').textContent    = d.full_name    || 'N/A';
            document.getElementById('headerPosition').textContent    = d.position     || 'N/A';
            const badge = document.getElementById('headerStatusBadge');
            badge.textContent = d.status_label;
            badge.className   = 'inline-block px-4 py-2 text-sm font-semibold rounded-full ' + d.status_class;
            const typeBadge = document.getElementById('headerTypeBadge');
            if (typeBadge) {
                const t = d.employee_type || 'Internal';
                typeBadge.textContent = t;
                typeBadge.className = 'inline-block mt-2 px-4 py-2 text-sm font-semibold rounded-full ' +
                    (t === 'External' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-700');
            }
            document.getElementById('headerEci').textContent         = d.eci          || 'N/A';
            document.getElementById('headerEmail').textContent       = d.email_work || 'N/A';
            document.getElementById('headerPhone').textContent       = d.phone        || 'N/A';
            document.getElementById('headerDepartment').textContent  = d.department   || 'N/A';
            document.getElementById('headerDivision').textContent    = d.division     || 'N/A';
            document.getElementById('headerSinceDate').textContent   = d.since_date   || 'N/A';
        } catch (e) {
            console.error('refreshHeader error', e);
        }
    };

    // Load employee basic data
    async function loadEmployeeBasicData(employeeId) {
        try {
            const response = await fetch(`/api/employees/${employeeId}/basic-data`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (result.success && result.data) {
                const basicData = result.data;

                // General Information
                setValue('title', basicData.title);
                setValue('firstName', basicData.first_name);
                setValue('lastName', basicData.last_name);
                setValue('nickName', basicData.nick_name);
                setValue('gender', basicData.gender);
                setValue('religion', basicData.religion);
                setValue('searchTerm1', basicData.search_term_1);
                setValue('searchTerm2', basicData.search_term_2);
                setValue('maritalStatus', basicData.marital_status);
                setValue('birthDate', basicData.birth_date);
                setValue('birthPlace', basicData.birth_place);
                setValue('sinceDate', basicData.since_date);

                // Employee Information
                setValue('personnelArea', basicData.personnel_area);
                setValue('personnelSubarea', basicData.personnel_subarea);
                setValue('employeeGroup', basicData.employee_group);
                setValue('employeeSubgroup', basicData.employee_subgroup);
                setValue('position', basicData.position);
                setValue('currentAssignment', basicData.current_assignment);
                setValue('division', basicData.division);
                setValue('department', basicData.department);
                setValue('directSupervision', basicData.direct_supervision);
                setValue('manager', basicData.manager);
                setValue('authorizationGroup', basicData.authorization_group);
                setValue('homeBase', basicData.home_base);

                // Status
                setCheckbox('block', basicData.block);
                setCheckbox('deletionFlag', basicData.deletion_flag);

                // Audit Information
                setText('createdBy', basicData.created_by);
                setText('createdOn', formatDateTime(basicData.created_on));
                setText('lastChangedBy', basicData.last_changed_by);
                setText('lastChangedOn', formatDateTime(basicData.last_changed_on));

            } else {
            }
        } catch (error) {
            console.error('Error loading basic data:', error);
            showNotification('Error loading basic data', 'error');
        }
    }

    // Save employee basic data
    async function saveEmployeeBasicData(employeeId) {
        const firstName = getValue('firstName').trim();
        if (!firstName) {
            showNotification('First Name is required', 'error');
            document.getElementById('firstName')?.focus();
            return;
        }
        const v = id => getValue(id) || null;
        const basicData = {
            title: v('title'),
            first_name: getValue('firstName'),
            last_name: v('lastName'),
            nick_name: v('nickName'),
            gender: v('gender'),
            religion: v('religion'),
            search_term_1: v('searchTerm1'),
            search_term_2: v('searchTerm2'),
            marital_status: v('maritalStatus'),
            birth_date: v('birthDate'),
            birth_place: v('birthPlace'),
            since_date: v('sinceDate'),
            personnel_area: getValue('personnelArea'),
            personnel_subarea: getValue('personnelSubarea'),
            employee_group: getValue('employeeGroup'),
            employee_subgroup: getValue('employeeSubgroup'),
            position: getValue('position'),
            current_assignment: getValue('currentAssignment'),
            division: getValue('division'),
            department: getValue('department'),
            direct_supervision: getValue('directSupervision'),
            manager: getValue('manager'),
            authorization_group: getValue('authorizationGroup'),
            home_base: getValue('homeBase'),
            block: getCheckbox('block'),
            deletion_flag: getCheckbox('deletionFlag')
        };

        try {
            const response = await fetch(`/api/employees/${employeeId}/basic-data`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(basicData)
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Basic data saved successfully!', 'success');
                loadEmployeeBasicData(employeeId);
                refreshHeader(employeeId);
            } else {
                const fieldLabels = {
                    first_name: 'First Name', last_name: 'Last Name', nick_name: 'Nick Name',
                    gender: 'Gender', religion: 'Religion', marital_status: 'Marital Status',
                    birth_date: 'Birth Date', birth_place: 'Birth Place', since_date: 'Since Date',
                    title: 'Title',
                };
                if (data.errors && typeof data.errors === 'object') {
                    const msgs = Object.entries(data.errors).map(([field, errs]) => {
                        const label = fieldLabels[field] || field.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        return `${label}: ${Array.isArray(errs) ? errs[0] : errs}`;
                    });
                    showNotification(msgs.join('\n'), 'error');
                } else {
                    showNotification(data.message || 'Failed to save', 'error');
                }
            }
        } catch (error) {
            console.error('Error saving basic data:', error);
            showNotification('An error occurred while saving', 'error');
        }
    }

    // Helper functions
    function getValue(id) {
        const el = document.getElementById(id);
        return el ? el.value : '';
    }

    function setValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'hidden' && el.closest && el.closest('.custom-dd')) {
            if (typeof setCustomDropdownValue === 'function') {
                setCustomDropdownValue(id, value || '');
                return;
            }
        }
        el.value = value || '';
    }

    function getCheckbox(id) {
        const el = document.getElementById(id);
        return el ? el.checked : false;
    }

    function setCheckbox(id, value) {
        const el = document.getElementById(id);
        if (el) el.checked = value === true || value === 1;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '-';
    }

    function formatDateTime(dateString) {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString('id-ID', {
            timeZone: 'Asia/Jakarta',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    }

    // showNotification tersedia secara global dari dashboard.blade.php

    // Ganti skeleton dengan konten asli. Idempotent — boleh dipanggil berkali-kali
    // (dipanggil normal setelah data awal masuk, dan oleh safety timeout).
    function revealProfilePage() {
        const skeleton = document.getElementById('profileSkeleton');
        const content  = document.getElementById('profileContent');
        if (!content || !content.classList.contains('is-loading')) return;
        if (skeleton) skeleton.remove();
        content.classList.remove('is-loading');
        content.classList.add('is-revealed');
    }

    // Jaring pengaman: kalau fetch data awal menggantung/gagal total, halaman
    // tetap harus muncul daripada user terjebak di skeleton selamanya.
    const profileRevealFallback = setTimeout(revealProfilePage, 8000);

    // Load data when page loads
    document.addEventListener('DOMContentLoaded', async function() {
        // Guard untuk kasus custom-dropdown.js gagal di-load di production.
        if (typeof initCustomDropdowns === 'function') {
            initCustomDropdowns();
        }
        try {
            await loadEmployeeBasicData(employeeId);
        } finally {
            clearTimeout(profileRevealFallback);
            revealProfilePage();
        }

        // HC-D50: peta butir → tab + kolom (dipakai tautan ?section=&field=<key> dari Onboarding HR dan kartu kesiapan).
        window.__onbFields = @json($onbFieldMap);

        // Pindah ke tab, isi otomatis dropdown (mis. tipe identitas), lalu gulir + sorot kolom isiannya.
        function focusOnboardingField(spec) {
            if (!spec || !spec.section) { return; }
            if (document.getElementById('section-' + spec.section)) { switchSection(spec.section); }
            setTimeout(function () {
                if (spec.prefill) {
                    Object.keys(spec.prefill).forEach(function (id) {
                        if (typeof setCustomDropdownValue === 'function') { setCustomDropdownValue(id, spec.prefill[id]); }
                        else { const e = document.getElementById(id); if (e) { e.value = spec.prefill[id]; } }
                    });
                }
                let el = null;
                try { el = spec.field ? document.querySelector(spec.field) : null; } catch (e) { el = null; }
                // Dropdown kustom: kolom aslinya <input type="hidden"> — sorot tombolnya.
                if (el && el.type === 'hidden') { el = (el.closest('.custom-dd') && el.closest('.custom-dd').querySelector('.custom-dd-btn')) || el; }
                const target = el || document.getElementById('section-' + spec.section);
                if (!target) { return; }
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (el) {
                    try { el.focus({ preventScroll: true }); } catch (e) { /* tidak dapat difokuskan */ }
                    el.classList.add('ring-2', 'ring-offset-2', 'ring-red-600');
                    setTimeout(function () { el.classList.remove('ring-2', 'ring-offset-2', 'ring-red-600'); }, 2400);
                }
            }, 150);
        }
        window.focusOnboardingField = focusOnboardingField;

        // HC-D27/D50: kartu kesiapan data — render dari data, segarkan setelah simpan/hapus berhasil di tab mana pun
        // (seksi menyimpan lewat AJAX, jadi halaman tidak dimuat ulang). Dibangun dengan textContent (tanpa innerHTML):
        // label/petunjuk datang dari konfigurasi. Dibatasi (debounce) dan hanya satu permintaan ringan.
        (function () {
            const banner = document.getElementById('onboardingBanner');
            if (!banner) { return; }
            let editable = [], initial = null;
            try { editable = JSON.parse(banner.dataset.editable || '[]'); initial = JSON.parse(banner.dataset.initial || 'null'); } catch (e) { return; }
            const META = {
                profile:  { icon: 'fa-id-card',        title: 'Personal data', note: 'Identity and contact details' },
                payroll:  { icon: 'fa-wallet',         title: 'Payroll',       note: 'Bank account and tax ID' },
                bpjs:     { icon: 'fa-shield-alt',     title: 'BPJS',          note: 'Insurance numbers' },
                contract: { icon: 'fa-file-signature', title: 'Contract',      note: 'Contract and join date' }
            };
            // Kelompok yang sedang terbuka — diingat selama halaman terbuka agar tidak menutup sendiri setelah simpan/segarkan.
            const openGroups = {};
            let timer = null, seq = 0;
            const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) { n.className = cls; } if (text !== undefined) { n.textContent = text; } return n; };

            function render(d) {
                document.getElementById('onbCount').textContent = d.done + ' of ' + d.total + ' items';
                document.getElementById('onbPercent').textContent = d.percent + '%';
                document.getElementById('onbBar').style.width = d.percent + '%';
                const sub = document.getElementById('onbSubtitle');
                sub.textContent = '';
                if (d.status === 'complete') {
                    sub.className = 'text-sm mt-0.5 text-green-700';
                    sub.appendChild(el('i', 'fas fa-check-circle'));
                    sub.appendChild(document.createTextNode(' All required data is filled in. Thank you!'));
                } else {
                    sub.className = 'text-sm mt-0.5 text-gray-600';
                    sub.textContent = 'Open a section to see what is missing, then select an item to go straight to it.';
                }

                const box = document.getElementById('onbGroups');
                box.textContent = '';
                box.className = 'grid grid-cols-1 md:grid-cols-2 gap-3 mt-4 items-start';
                Object.keys(d.groups || {}).forEach(function (gk) {
                    const g = d.groups[gk];
                    if (!g.total) { return; }
                    const meta = META[gk] || { icon: 'fa-list', title: g.label, note: '' };
                    const items = (d.items || []).filter(function (i) { return i.group === gk; });
                    const missing = items.filter(function (i) { return !i.done; });
                    const canFill = function (it) { return !it.hr_only && editable.indexOf(it.section) !== -1; };
                    const actionable = missing.filter(canFill);
                    const complete = missing.length === 0;
                    const withHr = !complete && actionable.length === 0;   // sisanya hanya bisa diisi HR
                    const isOpen = !complete && !!openGroups[gk];

                    const tone = complete ? 'border-green-200 bg-green-50/40' : (withHr ? 'border-gray-200 bg-white' : 'border-amber-200 bg-white');
                    const card = el('div', 'rounded-xl border ' + tone);

                    // Kepala kelompok: ikon, judul, ringkasan "Missing: …", lencana status, panah.
                    const head = el(complete ? 'div' : 'button', 'w-full text-left flex items-start justify-between gap-3 p-4' + (complete ? '' : ' rounded-xl hover:bg-gray-50/70 transition'));
                    if (!complete) {
                        head.type = 'button';
                        head.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    }
                    const left = el('div', 'flex items-start gap-3 min-w-0');
                    left.appendChild(el('i', 'fas ' + meta.icon + ' text-gray-500 mt-0.5'));
                    const ttl = el('div', 'min-w-0');
                    ttl.appendChild(el('p', 'text-sm font-semibold text-gray-900', meta.title));
                    if (complete) {
                        ttl.appendChild(el('p', 'text-xs text-gray-500', meta.note + ' · ' + g.done + ' of ' + g.total));
                    } else {
                        const names = missing.map(function (it) { return it.label + (canFill(it) ? '' : ' (HR)'); });
                        const shown = names.slice(0, 3).join(', ') + (names.length > 3 ? ' +' + (names.length - 3) + ' more' : '');
                        ttl.appendChild(el('p', 'text-xs text-gray-500 mt-0.5 break-words', 'Missing: ' + shown));
                    }
                    left.appendChild(ttl);
                    head.appendChild(left);

                    const right = el('div', 'flex items-center gap-2 flex-shrink-0');
                    let badgeCls = 'bg-green-100 text-green-800', badgeText = 'Done';
                    if (!complete && !withHr) { badgeCls = 'bg-amber-100 text-amber-800'; badgeText = actionable.length + ' to fill'; }
                    if (withHr) { badgeCls = 'bg-gray-100 text-gray-600'; badgeText = 'With HR'; }
                    right.appendChild(el('span', 'px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap ' + badgeCls, badgeText));
                    if (!complete) {
                        right.appendChild(el('i', 'fas fa-chevron-down text-xs text-gray-400 transition-transform' + (isOpen ? ' rotate-180' : '')));
                    }
                    head.appendChild(right);
                    card.appendChild(head);

                    if (!complete) {
                        // Isi kelompok: hanya butir yang BELUM terisi, satu baris tiap butir.
                        const list = el('ul', 'px-4 pb-4 space-y-1' + (isOpen ? '' : ' hidden'));
                        missing.forEach(function (it) {
                            const li = el('li');
                            if (canFill(it)) {
                                const bt = el('button', 'group w-full text-left flex items-start justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-amber-50');
                                bt.type = 'button';
                                const t = el('span');
                                t.appendChild(el('span', 'text-sm font-medium text-gray-900', it.label));
                                if (it.hint) { t.appendChild(el('span', 'block text-xs text-gray-500 mt-0.5', it.hint)); }
                                bt.appendChild(t);
                                bt.appendChild(el('i', 'fas fa-chevron-right text-[10px] text-gray-400 mt-1.5 group-hover:text-gray-700'));
                                bt.addEventListener('click', function () { focusOnboardingField(it); });
                                li.appendChild(bt);
                            } else {
                                const row = el('div', 'flex items-start justify-between gap-2 rounded-lg px-2 py-1.5');
                                const t = el('span');
                                t.appendChild(el('span', 'text-sm text-gray-600', it.label));
                                t.appendChild(el('span', 'block text-xs text-gray-400 mt-0.5', it.hint || 'Managed by HR.'));
                                row.appendChild(t);
                                const tag = el('span', 'inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5 whitespace-nowrap');
                                tag.appendChild(el('i', 'fas fa-lock text-[9px]'));
                                tag.appendChild(document.createTextNode(' HR'));
                                row.appendChild(tag);
                                li.appendChild(row);
                            }
                            list.appendChild(li);
                        });
                        card.appendChild(list);
                        head.addEventListener('click', function () {
                            openGroups[gk] = list.classList.contains('hidden');
                            list.classList.toggle('hidden', !openGroups[gk]);
                            head.setAttribute('aria-expanded', openGroups[gk] ? 'true' : 'false');
                            const chev = head.querySelector('.fa-chevron-down');
                            if (chev) { chev.classList.toggle('rotate-180', openGroups[gk]); }
                        });
                    }
                    box.appendChild(card);
                });
            }

            function refresh() {
                const mine = ++seq;
                fetch(banner.dataset.url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (j) { if (j && j.success && mine === seq) { render(j.data); } })
                    .catch(function () { /* kartu opsional: biarkan angka lama */ });
            }

            if (initial) { render(initial); }

            if (typeof window.showNotification === 'function') {
                const original = window.showNotification;
                window.showNotification = function (message, type) {
                    const result = original.apply(this, arguments);
                    if (type === 'success') { clearTimeout(timer); timer = setTimeout(refresh, 400); }
                    return result;
                };
            }
        })();

        // HC-D14: tautan ?section=<tab-id> (dari halaman Onboarding) membuka tab yang diminta.
        // Nilai tak dikenal diabaikan — tab pertama tetap terbuka.
        const requestedSection = new URLSearchParams(window.location.search).get('section');
        if (requestedSection && document.getElementById('section-' + requestedSection)) {
            switchSection(requestedSection);
        }
        // HC-D50: ?field=<kunci butir> (tombol "Fill in" di Onboarding HR) → sorot kolom isiannya.
        const requestedField = new URLSearchParams(window.location.search).get('field');
        if (requestedField && window.__onbFields && window.__onbFields[requestedField]) {
            focusOnboardingField(window.__onbFields[requestedField]);
        }
    });
</script>
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endsection

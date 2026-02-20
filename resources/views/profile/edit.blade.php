@extends('dashboard')

@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('content')
@php
    $type        = $sessionUser['type'] ?? 'employee';
    $isEmployee  = $type === 'employee';
    $isCustomer  = $type === 'customer';

    if ($isEmployee && $profile) {
        $displayName = trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? ''));
        $initials    = strtoupper(substr($profile->first_name ?? 'U', 0, 1) . substr($profile->last_name ?? '', 0, 1));
        $subtitle    = $profile->position ?? ($profile->role_name ?? 'Employee');
    } else {
        $displayName = trim(($profile->title ?? '') . ' ' . ($profile->name_1 ?? ''));
        $initials    = strtoupper(substr($profile->name_1 ?? 'C', 0, 2));
        $subtitle    = $profile->customer_category ?? 'Customer';
    }
@endphp

<div class="max-w-7xl mx-auto space-y-6">

    {{-- ===== HEADER CARD ===== --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-10 py-7 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-6">
                {{-- Avatar --}}
                <div class="w-20 h-20 rounded-2xl bg-red-50 border-2 border-red-100 flex items-center justify-center text-red-800 font-extrabold text-2xl select-none flex-shrink-0">
                    {{ $initials }}
                </div>
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 leading-tight">{{ $displayName ?: '-' }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $subtitle }}</p>
                    @if($authUser)
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-1.5">
                        <i class="fas fa-at"></i> {{ $authUser->username ?? '-' }}
                    </p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-3">
                @if($authUser && $authUser->last_login_at)
                <span class="text-xs text-gray-400 hidden sm:block">
                    Login terakhir: {{ \Carbon\Carbon::parse($authUser->last_login_at)->format('d M Y, H:i') }}
                </span>
                @endif
                <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold
                    {{ ($profile->is_active ?? false) ? 'bg-green-100 text-green-700 ring-1 ring-green-300' : 'bg-gray-100 text-gray-500 ring-1 ring-gray-300' }}">
                    <span class="w-2 h-2 rounded-full {{ ($profile->is_active ?? false) ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                    {{ ($profile->is_active ?? false) ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
    </div>

    {{-- ===== MAIN GRID ===== --}}
    <div class="grid grid-cols-12 gap-6">

        {{-- ===== LEFT SIDEBAR ===== --}}
        <div class="col-span-12 lg:col-span-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-24">
                {{-- Nav --}}
                <div class="p-4 space-y-1">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-3 mb-2">Menu</p>
                    <button onclick="switchTab('profile')" data-tab="profile"
                            class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left transition-all text-sm font-semibold bg-red-50 text-red-800 border-l-4 border-red-800">
                        <i class="fas fa-user w-4 text-center"></i>
                        <span>Informasi Pribadi</span>
                    </button>
                    <button onclick="switchTab('security')" data-tab="security"
                            class="profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left transition-all text-sm font-semibold text-gray-600 hover:bg-gray-50 border-l-4 border-transparent">
                        <i class="fas fa-shield-alt w-4 text-center"></i>
                        <span>Keamanan</span>
                    </button>
                </div>

                {{-- Account meta --}}
                @if($authUser)
                <div class="border-t border-gray-100 p-5 space-y-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Info Akun</p>

                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-at text-red-700 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-medium">Username</p>
                            <p class="text-sm text-gray-800 font-semibold break-all">{{ $authUser->username ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-envelope text-blue-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-medium">Email</p>
                            <p class="text-sm text-gray-800 font-semibold break-all">{{ $authUser->email ?? '-' }}</p>
                        </div>
                    </div>

                    @if($authUser->last_login_at)
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-clock text-amber-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-medium">Login Terakhir</p>
                            <p class="text-sm text-gray-800 font-semibold">{{ \Carbon\Carbon::parse($authUser->last_login_at)->format('d M Y') }}</p>
                            <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($authUser->last_login_at)->format('H:i') }}</p>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <i class="fas fa-calendar-check text-green-500 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-medium">Bergabung</p>
                            <p class="text-sm text-gray-800 font-semibold">
                                {{ $authUser->created_at ? \Carbon\Carbon::parse($authUser->created_at)->format('d M Y') : '-' }}
                            </p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- ===== RIGHT CONTENT ===== --}}
        <div class="col-span-12 lg:col-span-9 space-y-6">

            {{-- ==================== TAB: PROFILE ==================== --}}
            <div id="tab-profile">

                @if($isEmployee && $profile)

                    {{-- Data Pribadi --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                        <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                            <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-id-card text-red-800"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Data Pribadi</h3>
                                <p class="text-xs text-gray-400">Informasi identitas karyawan</p>
                            </div>
                        </div>
                        <div class="p-8">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @php
                                $fields = [
                                    ['icon' => 'fa-id-badge',    'label' => 'Employee ID (ECI)', 'value' => $profile->eci],
                                    ['icon' => 'fa-user',        'label' => 'Nama Depan',        'value' => $profile->first_name],
                                    ['icon' => 'fa-user',        'label' => 'Nama Belakang',     'value' => $profile->last_name],
                                    ['icon' => 'fa-smile',       'label' => 'Nama Panggilan',    'value' => $profile->nick_name],
                                    ['icon' => 'fa-venus-mars',  'label' => 'Jenis Kelamin',     'value' => $profile->gender],
                                    ['icon' => 'fa-pray',        'label' => 'Agama',             'value' => $profile->religion],
                                    ['icon' => 'fa-heart',       'label' => 'Status Perkawinan', 'value' => $profile->marital_status],
                                    ['icon' => 'fa-birthday-cake','label' => 'Tanggal Lahir',    'value' => $profile->birth_date ? \Carbon\Carbon::parse($profile->birth_date)->format('d M Y') : null],
                                    ['icon' => 'fa-map-marker-alt','label' => 'Tempat Lahir',   'value' => $profile->birth_place],
                                ];
                                @endphp
                                @foreach($fields as $f)
                                <div class="bg-gray-50 rounded-xl p-4 hover:bg-red-50/30 transition-colors">
                                    <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1.5">{{ $f['label'] }}</p>
                                    <p class="text-base text-gray-800 font-semibold">{{ $f['value'] ?? '-' }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Informasi Pekerjaan --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                        <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                            <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-briefcase text-blue-600"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Informasi Pekerjaan</h3>
                                <p class="text-xs text-gray-400">Posisi dan penempatan kerja</p>
                            </div>
                        </div>
                        <div class="p-8">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @php
                                $workFields = [
                                    ['label' => 'Jabatan',           'value' => $profile->position],
                                    ['label' => 'Divisi',            'value' => $profile->division],
                                    ['label' => 'Departemen',        'value' => $profile->department],
                                    ['label' => 'Employee Group',    'value' => $profile->employee_group],
                                    ['label' => 'Employee Subgroup', 'value' => $profile->employee_subgroup],
                                    ['label' => 'Role',              'value' => $profile->role_name],
                                    ['label' => 'Tanggal Bergabung', 'value' => $profile->since_date ? \Carbon\Carbon::parse($profile->since_date)->format('d M Y') : null],
                                ];
                                @endphp
                                @foreach($workFields as $f)
                                <div class="bg-gray-50 rounded-xl p-4 hover:bg-blue-50/30 transition-colors">
                                    <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1.5">{{ $f['label'] }}</p>
                                    <p class="text-base text-gray-800 font-semibold">{{ $f['value'] ?? '-' }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Kontak & Alamat --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                            <div class="w-9 h-9 bg-green-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-address-book text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Kontak & Alamat</h3>
                                <p class="text-xs text-gray-400">Informasi kontak dan tempat tinggal</p>
                            </div>
                        </div>
                        <div class="p-8">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @php
                                $contactFields = [
                                    ['label' => 'Email Login',     'value' => $authUser->email ?? null],
                                    ['label' => 'Email Pribadi',   'value' => $profile->email_personal],
                                    ['label' => 'Telepon Seluler', 'value' => $profile->cell_phone ?? $authUser->phone ?? null],
                                    ['label' => 'Telepon Kantor',  'value' => $profile->telephone],
                                    ['label' => 'Kode Pos',        'value' => $profile->postal_code],
                                ];
                                $alamat = collect([$profile->street, $profile->city, $profile->region, $profile->country])->filter()->implode(', ');
                                @endphp
                                @foreach($contactFields as $f)
                                <div class="bg-gray-50 rounded-xl p-4 hover:bg-green-50/30 transition-colors">
                                    <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1.5">{{ $f['label'] }}</p>
                                    <p class="text-base text-gray-800 font-semibold">{{ $f['value'] ?: '-' }}</p>
                                </div>
                                @endforeach
                                {{-- Alamat span 2 columns --}}
                                <div class="bg-gray-50 rounded-xl p-4 hover:bg-green-50/30 transition-colors sm:col-span-2 lg:col-span-1">
                                    <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1.5">Alamat</p>
                                    <p class="text-base text-gray-800 font-semibold">{{ $alamat ?: '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                @elseif($isCustomer && $profile)

                    {{-- Informasi Perusahaan --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                        <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                            <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-building text-red-800"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Informasi Perusahaan</h3>
                                <p class="text-xs text-gray-400">Data perusahaan dan kategori</p>
                            </div>
                        </div>
                        <div class="p-8">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @php
                                $custFields = [
                                    ['label' => 'Kode Pelanggan',    'value' => $profile->customer_code],
                                    ['label' => 'Nama Perusahaan 1', 'value' => $profile->name_1],
                                    ['label' => 'Nama Perusahaan 2', 'value' => $profile->name_2],
                                    ['label' => 'Kategori',          'value' => $profile->customer_category],
                                    ['label' => 'Grup Pelanggan',    'value' => $profile->customer_group],
                                    ['label' => 'Sektor Industri',   'value' => $profile->industry_sector],
                                    ['label' => 'Account Executive', 'value' => $profile->ec_account_executive],
                                ];
                                @endphp
                                @foreach($custFields as $f)
                                <div class="bg-gray-50 rounded-xl p-4 hover:bg-red-50/30 transition-colors">
                                    <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1.5">{{ $f['label'] }}</p>
                                    <p class="text-base text-gray-800 font-semibold">{{ $f['value'] ?? '-' }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Kontak --}}
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                            <div class="w-9 h-9 bg-green-50 rounded-xl flex items-center justify-center">
                                <i class="fas fa-address-book text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">Kontak</h3>
                                <p class="text-xs text-gray-400">Informasi kontak perusahaan</p>
                            </div>
                        </div>
                        <div class="p-8">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                @php
                                $contactCustFields = [
                                    ['label' => 'Email Login', 'value' => $authUser->email ?? $profile->email ?? null],
                                    ['label' => 'Telepon',     'value' => $authUser->phone ?? $profile->contact_phone ?? null],
                                    ['label' => 'Nama Kontak', 'value' => $profile->contact_name],
                                ];
                                @endphp
                                @foreach($contactCustFields as $f)
                                <div class="bg-gray-50 rounded-xl p-4 hover:bg-green-50/30 transition-colors">
                                    <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1.5">{{ $f['label'] }}</p>
                                    <p class="text-base text-gray-800 font-semibold">{{ $f['value'] ?: '-' }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                @else
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center text-gray-400">
                        <i class="fas fa-user-slash text-5xl mb-4 opacity-40"></i>
                        <p class="text-lg font-medium">Data profil tidak ditemukan.</p>
                    </div>
                @endif
            </div>

            {{-- ==================== TAB: SECURITY ==================== --}}
            <div id="tab-security" class="hidden space-y-6">

                {{-- Change Password --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-9 h-9 bg-amber-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-lock text-amber-600"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Ubah Password</h3>
                            <p class="text-xs text-gray-400">Gunakan password yang kuat dan belum digunakan di platform lain.</p>
                        </div>
                    </div>
                    <div class="p-8">

                        {{-- Alert --}}
                        <div id="pwAlert" class="hidden mb-6 p-4 rounded-xl text-sm font-medium"></div>

                        <form id="changePasswordForm" class="space-y-6 max-w-xl">
                            @csrf

                            {{-- Password Lama --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Password Lama</label>
                                <div class="relative">
                                    <input type="password" id="current_password" name="current_password"
                                           placeholder="Masukkan password saat ini"
                                           class="w-full pl-4 pr-12 py-3.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-700 focus:ring-4 focus:ring-red-700/10 transition-all bg-gray-50 focus:bg-white"
                                           autocomplete="current-password"/>
                                    <button type="button" onclick="togglePw('current_password')"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                </div>
                                <p id="err-current_password" class="hidden mt-1.5 text-xs text-red-600 font-medium"></p>
                            </div>

                            {{-- Password Baru --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Password Baru</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password"
                                           placeholder="Minimal 8 karakter"
                                           class="w-full pl-4 pr-12 py-3.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-700 focus:ring-4 focus:ring-red-700/10 transition-all bg-gray-50 focus:bg-white"
                                           autocomplete="new-password"/>
                                    <button type="button" onclick="togglePw('password')"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                </div>
                                <p id="err-password" class="hidden mt-1.5 text-xs text-red-600 font-medium"></p>
                            </div>

                            {{-- Konfirmasi --}}
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Konfirmasi Password Baru</label>
                                <div class="relative">
                                    <input type="password" id="password_confirmation" name="password_confirmation"
                                           placeholder="Ulangi password baru"
                                           class="w-full pl-4 pr-12 py-3.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-700 focus:ring-4 focus:ring-red-700/10 transition-all bg-gray-50 focus:bg-white"
                                           autocomplete="new-password"/>
                                    <button type="button" onclick="togglePw('password_confirmation')"
                                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                </div>
                                <p id="err-password_confirmation" class="hidden mt-1.5 text-xs text-red-600 font-medium"></p>
                            </div>

                            {{-- Strength hints --}}
                            <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                                <p class="text-xs font-bold text-gray-600 mb-3 uppercase tracking-wide">Syarat Password Baru</p>
                                <div class="space-y-2">
                                    <div id="hint-len" class="flex items-center gap-2.5 text-sm text-red-500">
                                        <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-xs flex-shrink-0">
                                            <i class="fas fa-times text-[9px]"></i>
                                        </span>
                                        Minimal 8 karakter
                                    </div>
                                    <div id="hint-match" class="flex items-center gap-2.5 text-sm text-red-500">
                                        <span class="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center text-xs flex-shrink-0">
                                            <i class="fas fa-times text-[9px]"></i>
                                        </span>
                                        Password cocok dengan konfirmasi
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button type="button" onclick="confirmChangePassword()"
                                        class="inline-flex items-center gap-2 px-7 py-3.5 bg-gradient-to-r from-red-800 to-red-900 text-white text-sm font-bold rounded-xl transition-all hover:shadow-lg hover:shadow-red-900/30 hover:-translate-y-0.5 active:translate-y-0">
                                    <i class="fas fa-key"></i>
                                    Ubah Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Aktivitas Akun --}}
                @if($authUser && $authUser->last_login_at)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-8 py-5 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-9 h-9 bg-purple-50 rounded-xl flex items-center justify-center">
                            <i class="fas fa-history text-purple-600"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Aktivitas Akun</h3>
                            <p class="text-xs text-gray-400">Riwayat aktivitas login</p>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="flex items-center gap-4 p-5 bg-gray-50 rounded-xl">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-sign-in-alt text-green-600 text-lg"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">Login Terakhir</p>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    {{ \Carbon\Carbon::parse($authUser->last_login_at)->isoFormat('dddd, D MMMM YYYY') }}
                                    &bull;
                                    {{ \Carbon\Carbon::parse($authUser->last_login_at)->format('H:i') }} WIB
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

        </div>{{-- end right --}}
    </div>{{-- end grid --}}
</div>

{{-- ===== CONFIRMATION MODAL ===== --}}
<div id="confirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" style="display:none">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="p-7">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-14 h-14 bg-amber-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-amber-600 text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-gray-900">Konfirmasi Ubah Password</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Tindakan ini tidak dapat dibatalkan.</p>
                </div>
            </div>
            <p class="text-sm text-gray-600 bg-amber-50 border border-amber-200 rounded-xl p-4 leading-relaxed">
                Apakah Anda yakin ingin mengubah password akun Anda?<br>
                Setelah berhasil, gunakan password baru untuk login berikutnya.
            </p>
        </div>
        <div class="border-t border-gray-100 px-7 py-4 flex justify-end gap-3 bg-gray-50">
            <button onclick="closeModal()"
                    class="px-6 py-2.5 text-sm font-bold text-gray-700 border-2 border-gray-200 rounded-xl hover:bg-white transition-all">
                Batal
            </button>
            <button id="confirmBtn" onclick="submitChangePassword()"
                    class="px-6 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-red-800 to-red-900 rounded-xl hover:shadow-lg transition-all flex items-center gap-2">
                <i class="fas fa-key"></i>
                Ya, Ubah Password
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ─── Tab Switching ────────────────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.profile-tab').forEach(btn => {
        const active = btn.dataset.tab === tab;
        btn.className = [
            'profile-tab w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left transition-all text-sm font-semibold border-l-4',
            active
                ? 'bg-red-50 text-red-800 border-red-800'
                : 'text-gray-600 hover:bg-gray-50 border-transparent'
        ].join(' ');
    });
    document.getElementById('tab-profile').classList.toggle('hidden', tab !== 'profile');
    document.getElementById('tab-security').classList.toggle('hidden', tab !== 'security');
}

// ─── Password Toggle ──────────────────────────────────────────────────────────
function togglePw(id) {
    const input = document.getElementById(id);
    const btn   = input.nextElementSibling.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        btn.className = 'fas fa-eye-slash text-sm';
    } else {
        input.type = 'password';
        btn.className = 'fas fa-eye text-sm';
    }
}

// ─── Strength Hints ───────────────────────────────────────────────────────────
const pwInput   = document.getElementById('password');
const confInput = document.getElementById('password_confirmation');

function updateHints() {
    const pw      = pwInput.value;
    const conf    = confInput.value;
    const lenOk   = pw.length >= 8;
    const matchOk = pw === conf && pw.length > 0;
    setHint('hint-len',   lenOk);
    setHint('hint-match', matchOk);
}

function setHint(id, ok) {
    const el   = document.getElementById(id);
    const icon = el.querySelector('span i');
    if (ok) {
        el.className  = el.className.replace('text-red-500', 'text-green-600');
        el.className  = 'flex items-center gap-2.5 text-sm text-green-600';
        icon.className = 'fas fa-check text-[9px]';
    } else {
        el.className  = 'flex items-center gap-2.5 text-sm text-red-500';
        icon.className = 'fas fa-times text-[9px]';
    }
}

pwInput.addEventListener('input', updateHints);
confInput.addEventListener('input', updateHints);

// ─── Field Errors ─────────────────────────────────────────────────────────────
function clearErrors() {
    ['current_password', 'password', 'password_confirmation'].forEach(id => {
        const err = document.getElementById('err-' + id);
        if (err) { err.textContent = ''; err.classList.add('hidden'); }
        document.getElementById(id)?.classList.remove('border-red-500');
    });
    document.getElementById('pwAlert').classList.add('hidden');
}

function showFieldError(field, msg) {
    const el = document.getElementById('err-' + field);
    if (el) { el.textContent = msg; el.classList.remove('hidden'); }
    document.getElementById(field)?.classList.add('border-red-500');
}

function showAlert(type, msg) {
    const alert = document.getElementById('pwAlert');
    if (type === 'success') {
        alert.className = 'mb-6 p-4 rounded-xl text-sm font-medium bg-green-50 border border-green-200 text-green-700 flex items-center gap-2';
        alert.innerHTML = '<i class="fas fa-check-circle text-lg"></i><span>' + msg + '</span>';
    } else {
        alert.className = 'mb-6 p-4 rounded-xl text-sm font-medium bg-red-50 border border-red-200 text-red-700 flex items-center gap-2';
        alert.innerHTML = '<i class="fas fa-exclamation-circle text-lg"></i><span>' + msg + '</span>';
    }
    alert.classList.remove('hidden');
}

// ─── Modal ────────────────────────────────────────────────────────────────────
function confirmChangePassword() {
    clearErrors();
    const cur  = document.getElementById('current_password').value.trim();
    const pw   = document.getElementById('password').value;
    const conf = document.getElementById('password_confirmation').value;

    let hasError = false;
    if (!cur)          { showFieldError('current_password', 'Password lama wajib diisi'); hasError = true; }
    if (pw.length < 8) { showFieldError('password', 'Password baru minimal 8 karakter'); hasError = true; }
    if (pw !== conf)   { showFieldError('password_confirmation', 'Konfirmasi password tidak cocok'); hasError = true; }
    if (hasError) return;

    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ─── Submit ───────────────────────────────────────────────────────────────────
async function submitChangePassword() {
    const btn = document.getElementById('confirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    closeModal();

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const body = {
        current_password:      document.getElementById('current_password').value,
        password:              document.getElementById('password').value,
        password_confirmation: document.getElementById('password_confirmation').value,
    };

    try {
        const res  = await fetch('{{ route("profile.change-password") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (data.success) {
            showAlert('success', data.message || 'Password berhasil diubah.');
            document.getElementById('changePasswordForm').reset();
            updateHints();
        } else {
            showAlert('error', data.message || 'Gagal mengubah password.');
            if (data.errors) {
                Object.entries(data.errors).forEach(([field, msgs]) => showFieldError(field, msgs[0]));
            }
        }
    } catch {
        showAlert('error', 'Terjadi kesalahan jaringan. Silakan coba lagi.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-key"></i> Ya, Ubah Password';
    }
}
</script>
@endpush

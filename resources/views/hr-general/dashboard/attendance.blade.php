{{--
    BLOK ATTENDANCE DI HALAMAN DASHBOARD
    ====================================================================
    Disisipkan dari `home/home.blade.php` dengan SATU baris @include.

    Isinya sengaja ditaruh di sini, bukan di berkas dashboard, karena
    `home.blade.php` dan `DashboardController` adalah berkas produksi di
    luar kontrak berkas yang boleh disentuh modul ini. Angka-angkanya pun
    tidak dirender di sini melainkan diambil lewat fetch() dari
    `general.dashboard.attendance`, sehingga pemuatan dashboard tidak ikut
    menanggung query presensi.

    Yang dirender di server hanyalah identitas dari sesi (nol query) dan
    rangka kartunya. Susunan blok: HERO -> sisi HR -> sisi pribadi, sesuai
    keputusan D117.

    Warna kartu hero memakai `primary-surface` — ikut Accent color dan
    Sidebar style di Settings. Jangan menggantinya dengan warna patok;
    lihat docs/updated-file/07-KONVENSI-UI.md.
--}}

@php
    $hour       = now()->hour;
    $greeting   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName  = explode(' ', $user['name'] ?? 'User')[0];
    $roleName   = $user['role']['name'] ?? 'User';

    $canSelf    = $can('general.my-attendance');
    $canRecap   = $can('general.attendance');

    // Leave & Permit adalah modul TIM, bukan modul ini, jadi penjagaannya
    // meniru persis apa yang dipakai sidebar — bukan aturan baru buatan sendiri:
    //   sisi HR       -> slug `general` / `hr_general.leave_permit`
    //   sisi karyawan -> sakelar ESS `my_leave_permit`
    // `$essConfig` hanya hidup di layout (Blade merender bagian halaman anak
    // SEBELUM layout dijalankan), jadi sakelarnya dibaca ulang di sini dari
    // sumber yang sama, bukan ditebak.
    $canLeaveAdmin = $can('general') || $can('hr_general.leave_permit');
    $essMenu       = \App\Http\Controllers\Management\EssSettingsController::getEssSettings();
    $canLeaveSelf  = !empty($essMenu['my_leave_permit']);

    // Kartu statistik pribadi. Label & satuannya disamakan persis dengan
    // halaman My Attendance supaya angka yang sama tidak pernah muncul
    // dengan dua nama berbeda.
    $selfStats = [
        ['id' => 'dashAttPresent',  'label' => 'Present This Month', 'hint' => 'days recorded'],
        ['id' => 'dashAttLate',     'label' => 'Late',               'hint' => 'days late'],
        ['id' => 'dashAttWork',     'label' => 'Work Hours',         'hint' => 'this month'],
        ['id' => 'dashAttOvertime', 'label' => 'Overtime Hours',     'hint' => 'this month'],
    ];

    // Pintasan sisi HR. Tiap ubin dijaga slug-nya sendiri: ubin yang tidak
    // dapat dibuka lebih membingungkan daripada ubin yang tidak ada.
    $hrTiles = [];
    if ($canRecap) {
        $hrTiles[] = ['href' => route('general.attendance.daily'), 'icon' => 'fa-clipboard-list', 'bg' => 'bg-blue-50', 'color' => 'text-blue-600', 'title' => 'Attendance Recap', 'desc' => "Today's attendance for every employee."];
    }
    if ($can('general.attendance.monthly')) {
        $hrTiles[] = ['href' => route('general.attendance.monthly'), 'icon' => 'fa-calendar-days', 'bg' => 'bg-indigo-50', 'color' => 'text-indigo-600', 'title' => 'Monthly Recap', 'desc' => 'Per-employee matrix for the running period.'];
    }
    if ($can('general.attendance.correction')) {
        $hrTiles[] = ['href' => route('general.attendance.corrections.index'), 'icon' => 'fa-pen-to-square', 'bg' => 'bg-amber-50', 'color' => 'text-amber-600', 'title' => 'Attendance Corrections', 'desc' => 'Review time corrections submitted by employees.', 'badge' => 'dashAttPendingBadge'];
    }
    if ($canLeaveAdmin) {
        $hrTiles[] = ['href' => route('hr-general.leave-permit'), 'icon' => 'fa-calendar-minus', 'bg' => 'bg-pink-50', 'color' => 'text-pink-600', 'title' => 'Leave & Permit', 'desc' => 'Review leave and permit applications from employees.'];
    }
    if ($can('general.settings.attendance')) {
        $hrTiles[] = ['href' => route('general.settings.attendance.edit'), 'icon' => 'fa-sliders', 'bg' => 'bg-gray-100', 'color' => 'text-gray-600', 'title' => 'Attendance Settings', 'desc' => 'Geofence mode, tolerance, and attendance rules.'];
    }

    // Pintasan sisi karyawan — presensi + Leave & Permit.
    //
    // Overtime dan Reimbursement SENGAJA tidak ada di sini meski slug-nya
    // dimiliki hampir semua karyawan: blok ini berfokus pada kehadiran harian,
    // dan keduanya sudah punya item tingkat atas sendiri di sidebar. Menaruhnya
    // lagi di sini hanya menggandakan pintu yang sama.
    $selfTiles = [];
    if ($canSelf) {
        $selfTiles[] = ['href' => route('general.my-attendance.index'), 'icon' => 'fa-fingerprint', 'bg' => 'bg-green-50', 'color' => 'text-green-600', 'title' => 'Check-in / Check-out', 'desc' => "Check in, check out, and view today's attendance status."];
        $selfTiles[] = ['href' => route('general.my-attendance.index'), 'icon' => 'fa-clock-rotate-left', 'bg' => 'bg-sky-50', 'color' => 'text-sky-600', 'title' => 'Attendance History', 'desc' => 'View your personal history and submit corrections.'];
    }
    if ($can('my-leave-permit') || $can('hr_general.leave_permit') || $can('general')) {
        $selfTiles[] = ['href' => route('my-leave-permit'), 'icon' => 'fa-calendar-check', 'bg' => 'bg-purple-50', 'color' => 'text-purple-600', 'title' => 'Leave & Permit', 'desc' => 'Apply for leave, permit, or view request history.'];
    }
    if ($canLeaveSelf) {
        $selfTiles[] = ['href' => route('my-leave-permit'), 'icon' => 'fa-calendar-minus', 'bg' => 'bg-pink-50', 'color' => 'text-pink-600', 'title' => 'Leave & Permit', 'desc' => 'Submit a leave or permit request and track its approval.'];
    }

    $tileClass = 'group relative flex flex-col gap-2 p-4 rounded-xl bg-white border border-gray-200 shadow-sm hover:shadow-md hover:border-gray-300 transition-all';
@endphp

{{-- ── HERO — menggantikan sapaan teks polos ──────────────────────────── --}}
<div class="primary-surface rounded-2xl p-5 sm:p-6 shadow-sm text-white">
    <div class="flex flex-col lg:flex-row lg:items-stretch lg:justify-between gap-5">

        <div class="min-w-0 flex flex-col justify-center">
            <span class="inline-flex items-center gap-2 self-start bg-white bg-opacity-15 text-xs font-semibold px-3 py-1 rounded-full mb-3 backdrop-blur-sm">
                <i class="fas fa-wand-magic-sparkles text-[10px]"></i> Employee daily home
            </span>
            <h2 class="text-xl sm:text-2xl font-bold truncate">{{ $greeting }}, {{ $firstName }}</h2>
            <p class="text-sm text-white text-opacity-80 mt-1">
                {{ $roleName }}@if(!empty($user['position'])) &middot; {{ $user['position'] }}@endif
            </p>
            <p class="text-xs text-white text-opacity-70 mt-0.5">
                {{ now()->translatedFormat('dddd, d F Y') }}
                <span id="dashAttShift"></span>
            </p>

            <!-- Quick Access Buttons (Mobile & Desktop) -->
            <div class="mt-4 flex flex-wrap gap-2">
                @if($can('my-leave-permit') || $can('hr_general.leave_permit') || $can('general'))
                <a href="{{ route('my-leave-permit') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur-sm shadow-sm border border-white/25 active:scale-95">
                    <i class="fas fa-calendar-check text-xs"></i> Apply Leave & Permit
                </a>
                @endif
                @if($canSelf)
                <a href="{{ route('general.my-attendance.index') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-semibold transition backdrop-blur-sm shadow-sm border border-white/25 active:scale-95">
                    <i class="fas fa-fingerprint text-xs"></i> My Attendance
                </a>
                @endif
            </div>
        </div>

        @if($canSelf)
        {{-- Kartu presensi hari ini --}}
        <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm w-full lg:max-w-md shrink-0 text-gray-900">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <p class="text-sm font-bold text-gray-900">Today's Attendance</p>
                    <span id="dashAttBadge" class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 text-gray-600">Loading…</span>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-400">{{ now()->translatedFormat('l') }}</p>
                    <p class="text-sm font-bold text-gray-800">{{ now()->translatedFormat('d F Y') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div class="border border-gray-200 rounded-lg p-2.5 sm:p-3">
                    <p class="text-xs text-gray-500 mb-1">Check-in</p>
                    <p class="text-lg sm:text-xl font-bold text-gray-900" id="dashAttCheckIn">–</p>
                </div>
                <div class="border border-gray-200 rounded-lg p-2.5 sm:p-3">
                    <p class="text-xs text-gray-500 mb-1">Check-out</p>
                    <p class="text-lg sm:text-xl font-bold text-gray-900" id="dashAttCheckOut">–</p>
                </div>
            </div>

            <!-- Original Check-in / Check-out button -->
            <a href="{{ route('general.my-attendance.index') }}"
               class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-lg hover:bg-black transition-all">
                <i class="fas fa-fingerprint"></i> Open details
            </a>

            <!-- Dedicated div below attendance for Leave & Permit -->
            @if($can('my-leave-permit') || $can('hr_general.leave_permit') || $can('general'))
            <div class="mt-3 pt-3 border-t border-gray-100">
                <a href="{{ route('my-leave-permit') }}"
                   class="flex items-center justify-between gap-2 w-full px-3.5 py-2.5 bg-purple-50 border border-purple-200 text-purple-800 hover:bg-purple-100 rounded-lg transition-all text-xs font-semibold group shadow-sm active:scale-95">
                    <span class="flex items-center gap-2">
                        <span class="w-6.5 h-6.5 rounded-md bg-purple-700 text-white flex items-center justify-center text-[11px] shadow-sm">
                            <i class="fas fa-calendar-plus"></i>
                        </span>
                        <span>Apply Leave & Permit</span>
                    </span>
                    <span class="flex items-center gap-1 text-[11px] text-purple-600 font-medium">
                        Apply now <i class="fas fa-chevron-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
                    </span>
                </a>
            </div>
            @endif
        </div>
        @endif

    </div>
</div>

{{-- ── SISI HR ────────────────────────────────────────────────────────── --}}
@if($canRecap)
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
    <div class="flex items-center justify-between gap-3 mb-1">
        <p class="text-sm font-semibold text-gray-800">Easy Access Daily</p>
        <a href="{{ route('general.attendance.daily') }}" class="text-xs font-semibold primary-text hover:underline">Attendance Recap &rarr;</a>
    </div>
    <p class="text-xs text-gray-400 mb-4">Quick shortcuts for daily access.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach($hrTiles as $tile)
        <a href="{{ $tile['href'] }}" class="{{ $tileClass }}">
            @if(!empty($tile['badge']))
            <span id="{{ $tile['badge'] }}" class="hidden absolute top-3 right-3 min-w-[1.25rem] h-5 px-1.5 bg-amber-500 text-white text-[10px] font-bold rounded-full items-center justify-center"></span>
            @endif
            <div class="w-9 h-9 rounded-xl {{ $tile['bg'] }} flex items-center justify-center">
                <i class="fas {{ $tile['icon'] }} {{ $tile['color'] }} text-sm"></i>
            </div>
            <p class="text-sm font-semibold text-gray-800 leading-tight">{{ $tile['title'] }}</p>
            <p class="text-xs text-gray-400 leading-snug">{{ $tile['desc'] }}</p>
        </a>
        @endforeach
    </div>

    {{-- Ringkasan hari ini se-perusahaan.
         Absent SENGAJA tidak ditampilkan: selama modul Cuti belum ada, angka
         itu hanya dapat ditebak dan tebakannya menuduh karyawan yang sedang
         cuti sebagai alpa. --}}
    <div class="mt-5 pt-5 border-t border-gray-100">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Today's Attendance &mdash; All Employees</p>
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach([
                ['id' => 'dashAttHrRecorded',  'label' => 'Recorded',    'hint' => 'rows today'],
                ['id' => 'dashAttHrCheckedIn', 'label' => 'Checked in',  'hint' => 'employees'],
                ['id' => 'dashAttHrStillIn',   'label' => 'Still In',    'hint' => 'no check-out yet'],
                ['id' => 'dashAttHrLate',      'label' => 'Late',        'hint' => 'employees'],
            ] as $stat)
            <div class="border border-gray-200 rounded-xl p-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1" id="{{ $stat['id'] }}">–</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $stat['hint'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ── SISI PRIBADI ───────────────────────────────────────────────────── --}}
@if($canSelf)

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach($selfStats as $stat)
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
        <p class="text-2xl font-bold text-gray-900 mt-1" id="{{ $stat['id'] }}">–</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ $stat['hint'] }}</p>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <p class="text-sm font-semibold text-gray-800">Daily Access</p>
        <p class="text-xs text-gray-400 mb-4">Quick shortcuts for daily access.</p>
        {{-- Tersisa dua ubin, jadi pada layar lebar keduanya DITUMPUK, bukan
             berdampingan: dua ubin berdampingan di kolom sempit menyisakan
             ruang kosong besar di bawahnya, dan kartu ini berdiri di samping
             riwayat yang jauh lebih tinggi. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-3">
            @foreach($selfTiles as $tile)
            <a href="{{ $tile['href'] }}" class="{{ $tileClass }}">
                <div class="w-9 h-9 rounded-xl {{ $tile['bg'] }} flex items-center justify-center">
                    <i class="fas {{ $tile['icon'] }} {{ $tile['color'] }} text-sm"></i>
                </div>
                <p class="text-sm font-semibold text-gray-800 leading-tight">{{ $tile['title'] }}</p>
                <p class="text-xs text-gray-400 leading-snug">{{ $tile['desc'] }}</p>
            </a>
            @endforeach
        </div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <p class="text-sm font-semibold text-gray-800">My Attendance History</p>
                <p class="text-xs text-gray-400">Your last 7 recorded days.</p>
            </div>
            <a href="{{ route('general.my-attendance.index') }}"
               class="text-xs font-semibold px-3 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition-all whitespace-nowrap">
                Open details
            </a>
        </div>
        <div id="dashAttHistory" class="space-y-3">
            <p class="text-sm text-gray-400 py-8 text-center">Loading…</p>
        </div>
    </div>

</div>

@endif

{{-- Skrip hanya dimuat bila ada yang perlu diisi. Pengguna tanpa satu pun izin
     presensi tetap mendapat kartu hero-nya, tanpa permintaan HTTP tambahan. --}}
@if($canSelf || $canRecap)
@push('scripts')
<script>
(function () {
    // Blok Attendance diisi setelah halaman tampil supaya pemuatan dashboard
    // tidak ikut menunggu query presensi. Kegagalan sengaja DIAM: dashboard
    // masih memuat tiket dan modul lain, dan pemberitahuan galat di sini hanya
    // menakut-nakuti tanpa ada yang bisa dilakukan pengguna.
    const text = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    /** Menit -> "7 h 30 m". Sama dengan format di halaman My Attendance. */
    function duration(minutes) {
        if (!minutes || minutes <= 0) return '0 m';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return ((h > 0 ? h + ' h ' : '') + (m > 0 ? m + ' m' : '')).trim();
    }

    /** Badge status hari ini. Kuning = perlu ditinjau, BUKAN kesalahan. */
    function todayBadge(record) {
        if (!record || !record.check_in_at) return ['Not checked in', 'bg-gray-100 text-gray-600'];
        if (!record.check_out_at)            return ['Checked in',     'bg-blue-100 text-blue-700'];
        if (record.late_minutes > 0)         return ['Completed, late ' + record.late_minutes + ' m', 'bg-amber-100 text-amber-700'];
        return ['Completed', 'bg-green-100 text-green-700'];
    }

    function renderSelf(self) {
        if (!self) return;

        const record = self.record;
        text('dashAttCheckIn',  record && record.check_in_at  ? record.check_in_at  : '–');
        text('dashAttCheckOut', record && record.check_out_at ? record.check_out_at : '–');

        const badge = document.getElementById('dashAttBadge');
        if (badge) {
            const [label, cls] = todayBadge(record);
            badge.textContent = label;
            badge.className = 'inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded ' + cls;
        }

        if (self.shift) {
            text('dashAttShift', ' · shift ' + self.shift.name + ' (' + self.shift.time_range + ')');
        }

        const s = self.summary || {};
        text('dashAttPresent',  s.present ?? 0);
        text('dashAttLate',     s.late ?? 0);
        text('dashAttWork',     duration(s.work_minutes));
        text('dashAttOvertime', duration(s.overtime_minutes));

        renderHistory(self.history || []);
    }

    function renderHistory(rows) {
        const box = document.getElementById('dashAttHistory');
        if (!box) return;

        if (!rows.length) {
            box.innerHTML = '<p class="text-sm text-gray-400 py-8 text-center">No attendance recorded in the last 7 days.</p>';
            return;
        }

        box.innerHTML = rows.map(function (r) {
            const late = r.late > 0;
            const badgeCls = late ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
            const badgeTxt = late ? 'Late ' + r.late + ' m' : 'Present';

            const cell = (label, value) =>
                '<div class="bg-gray-50 border border-gray-100 rounded-lg px-3 py-2">' +
                    '<p class="text-[10px] text-gray-400">' + label + '</p>' +
                    '<p class="text-sm font-semibold text-gray-800">' + (value || '–') + '</p>' +
                '</div>';

            return '' +
                '<div class="border border-gray-200 rounded-xl p-3">' +
                    '<div class="flex items-start justify-between gap-3 mb-2">' +
                        '<div>' +
                            '<p class="text-sm font-bold text-gray-800">' + r.date + '</p>' +
                            '<p class="text-xs text-gray-400">' + r.day + '</p>' +
                        '</div>' +
                        '<span class="px-2 py-0.5 text-xs font-semibold rounded ' + badgeCls + '">' + badgeTxt + '</span>' +
                    '</div>' +
                    '<div class="grid grid-cols-3 gap-2">' +
                        cell('Check-in', r.check_in) +
                        cell('Check-out', r.check_out) +
                        cell('Overtime', r.overtime) +
                    '</div>' +
                '</div>';
        }).join('');
    }

    function renderAdmin(admin) {
        if (!admin) return;

        text('dashAttHrRecorded',  admin.recorded);
        text('dashAttHrCheckedIn', admin.checked_in);
        text('dashAttHrStillIn',   admin.still_in);
        text('dashAttHrLate',      admin.late);

        const pending = document.getElementById('dashAttPendingBadge');
        if (pending && admin.pending_corrections > 0) {
            pending.textContent = admin.pending_corrections > 99 ? '99+' : admin.pending_corrections;
            pending.classList.remove('hidden');
            pending.classList.add('flex');
        }
    }

    fetch('{{ route('general.dashboard.attendance') }}', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            renderAdmin(res.data.admin);
            renderSelf(res.data.self);
        })
        .catch(() => {
            text('dashAttBadge', 'Unavailable');
            renderHistory([]);
        });
})();
</script>
@endpush
@endif

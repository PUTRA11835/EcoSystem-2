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

    Yang dirender di server hanyalah identitas dan rangka kartunya. Susunan
    blok: HERO -> sisi HR -> sisi pribadi, sesuai keputusan D117.

    🔴 D181 — SAPAAN memakai nick_name dari MASTER EMPLOYEE (satu query by id,
    bukan lagi "nol query"), BUKAN dari `$user['name']` di sesi. Sebelumnya
    baris ini memotong kata pertama nama lengkap sesi — berbeda dari My
    Attendance yang selalu memanggil nick_name langsung dari
    `employee_basic_data`. Kalau nick_name berbeda dari kata pertama nama
    lengkap (kasus umum), Dashboard dan My Attendance menyapa dengan nama yang
    berbeda untuk orang yang sama. Satu query tambahan (primary key, murah)
    dianggap sepadan demi sapaan yang konsisten di kedua halaman.

    Warna kartu hero memakai `primary-surface` — ikut Accent color dan
    Sidebar style di Settings. Jangan menggantinya dengan warna patok;
    lihat docs/updated-file/07-KONVENSI-UI.md.
--}}

@php
    $hour       = now()->hour;
    $greeting   = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

    // D181 — nick_name dari master employee, sama seperti My Attendance.
    // Fallback ke potongan nama sesi HANYA bila baris basic data-nya hilang
    // (seharusnya tidak pernah terjadi untuk akun yang sah).
    $firstName  = \App\Models\Employee::find($user['id'] ?? 0)?->basicData?->nick_name
        ?: explode(' ', $user['name'] ?? 'User')[0];
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
        $hrTiles[] = ['href' => route('hr-general.leave-permit'), 'icon' => 'fa-calendar-minus', 'bg' => 'bg-pink-50', 'color' => 'text-pink-600', 'title' => 'Leave & Permit Review', 'desc' => 'Review leave and permit applications from employees.'];
    }
    if ($can('general.settings.attendance')) {
        $hrTiles[] = ['href' => route('general.attendance.settings.edit'), 'icon' => 'fa-sliders', 'bg' => 'bg-gray-100', 'color' => 'text-gray-600', 'title' => 'Attendance Settings', 'desc' => 'Geofence mode, tolerance, and attendance rules.'];
    }

    // Pintasan sisi karyawan — presensi + Leave & Permit.
    //
    // Overtime dan Reimbursement SENGAJA tidak ada di sini meski slug-nya
    // dimiliki hampir semua karyawan: blok ini berfokus pada kehadiran harian,
    // dan keduanya sudah punya item tingkat atas sendiri di sidebar. Menaruhnya
    // lagi di sini hanya menggandakan pintu yang sama.
    $selfTiles = [];
    if ($canSelf) {
        // Tile 'Check-in / Check-out' dihapus: aksinya kini tombol utama kontekstual di header (satu pintu, bukan empat).
        $selfTiles[] = ['href' => route('general.my-attendance.index'), 'icon' => 'fa-clock-rotate-left', 'bg' => 'bg-sky-50', 'color' => 'text-sky-600', 'title' => 'Attendance History', 'desc' => 'View your personal history and submit corrections.'];
    }
    if ($can('my-leave-permit') || $can('hr_general.leave_permit') || $can('general')) {
        $selfTiles[] = ['href' => route('my-leave-permit'), 'icon' => 'fa-calendar-check', 'bg' => 'bg-purple-50', 'color' => 'text-purple-600', 'title' => 'My Leave & Permit', 'desc' => 'Track the status of your leave and permit requests.'];
    }
    // Tile ke-2 hanya bila tile pertama (izin Leave) tidak tampil, supaya tak ada dua tile identik.
    if ($canLeaveSelf && !($can('my-leave-permit') || $can('hr_general.leave_permit') || $can('general'))) {
        $selfTiles[] = ['href' => route('my-leave-permit'), 'icon' => 'fa-calendar-minus', 'bg' => 'bg-pink-50', 'color' => 'text-pink-600', 'title' => 'My Leave & Permit', 'desc' => 'Track the status of your leave and permit requests.'];
    }

    // Placeholder pemuatan: menggantikan "–" yang menyesatkan (terlihat seperti data kosong). Dihapus otomatis saat
    // text() mengisi nilai; bila fetch gagal, diganti "–" (lihat clearSkeletons()).
    $skSm = '<span class="inline-block h-4 w-12 animate-pulse rounded bg-gray-200 align-middle" aria-hidden="true"></span>';
    $sk = '<span class="inline-block h-6 w-16 animate-pulse rounded bg-gray-200 align-middle" aria-hidden="true"></span>';

    // Zona waktu PERUSAHAAN (config app.timezone), bukan zona browser — satu sumber untuk sapaan, jam, dan progres shift.
    $tz       = config('app.timezone');
    $tzLabel  = ['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'][$tz] ?? $tz;

    // Grid tile: auto-fit di lebar penuh; saat dua kartu berdampingan (2xl) -> 2 kolom dan tile ganjil terakhir
    // direntangkan, jadi tak ada sel kosong di baris terakhir.
    $tilesGrid = 'grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-[repeat(auto-fit,minmax(15rem,1fr))]'
        . (($canRecap && $canSelf) ? ' 2xl:grid-cols-2 2xl:[&>a:last-child:nth-child(odd)]:col-span-2' : '');

    $tileClass = 'group relative flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-3.5 transition hover:border-gray-300 hover:bg-gray-50 hover:shadow-sm';
@endphp

{{-- ── HEADER HALAMAN ─────────────────────────────────────────────────────
     Pola kartu "identitas + strip status" (Linear/Notion/Stripe):
       baris atas  = siapa saya + aksi utama
       strip bawah = status presensi hari ini, satu baris, label di kiri nilai di kanan
     Satu kelompok informasi per baris, rata kiri, tanpa kolom yang berdesakan.
     Warna merek hanya pada avatar dan tombol utama (mengikuti Accent di Settings). --}}
@php $canLeaveAny = $can('my-leave-permit') || $can('hr_general.leave_permit') || $can('general'); @endphp
<section aria-label="Welcome" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

    <div class="flex flex-col gap-4 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">

        {{-- Identitas --}}
        <div class="flex min-w-0 items-center gap-4">
            <span class="primary-surface flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-lg font-bold text-white shadow-sm">
                {{ \App\Support\Initials::make($user['name'] ?? $firstName, 'U') }}
            </span>
            <div class="min-w-0">
                {{-- 'l' = nama hari penuh pada translatedFormat() (token date() PHP, BUKAN moment.js:
                     'dddd' pernah menghasilkan "03030303"). --}}
                <p class="text-xs font-medium text-gray-500">
                    {{ now()->translatedFormat('l, d F Y') }}
                    <span class="mx-1 text-gray-300">&middot;</span>
                    <span class="tabular-nums"><span id="dashHeroClock">{{ now()->format('H:i') }}</span> {{ $tzLabel }}</span>
                </p>
                <h2 class="mt-0.5 truncate text-2xl font-bold leading-tight tracking-tight text-gray-900">{{ $greeting }}, {{ $firstName }}</h2>
            </div>
        </div>

        {{-- Aksi --}}
        @if($canLeaveAny || $canSelf)
        <div class="flex shrink-0 flex-wrap items-center gap-2.5">
            @if($canLeaveAny)
            <a href="{{ route('my-leave-permit') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 active:scale-95">
                <i class="fas fa-calendar-check text-xs text-gray-500"></i> Apply Leave &amp; Permit
            </a>
            @endif
            @if($canSelf)
            <a href="{{ route('general.my-attendance.index') }}"
               class="primary-surface inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 active:scale-95">
                <i id="dashAttCtaIcon" class="fas fa-fingerprint text-xs"></i> <span id="dashAttCta">Open attendance</span>
            </a>
            @endif
        </div>
        @endif
    </div>

    @if($canSelf)
    {{-- Panel presensi hari ini — SENGAJA menonjol: check-in/check-out adalah kewajiban harian, jadi angka dibuat besar dan
         panel berubah menjadi pengingat (kuning) saat ada yang terlewat. Status warna selalu disertai teks (bukan warna saja). --}}
    <div id="dashAttBand" class="border-t border-gray-100 bg-gray-50/60 px-5 py-4 transition-colors sm:px-6">
        <div class="flex flex-wrap items-center gap-x-10 gap-y-4">

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Today's attendance</p>
                <span id="dashAttBadge" class="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Loading…</span>
            </div>

            <div class="flex items-center gap-9">
                <div>
                    <p class="flex items-center gap-1.5 text-xs font-medium text-gray-500">
                        <i class="fas fa-arrow-right-to-bracket text-xs text-emerald-600 w-3.5 text-center"></i> Check-in
                    </p>
                    <p class="mt-1 pl-5 text-xl font-semibold leading-none tabular-nums text-gray-900" id="dashAttCheckIn">{!! $sk !!}</p>
                </div>
                <span class="h-8 w-px bg-gray-200"></span>
                <div>
                    <p class="flex items-center gap-1.5 text-xs font-medium text-gray-500">
                        <i class="fas fa-arrow-right-from-bracket text-xs text-rose-500 w-3.5 text-center"></i> Check-out
                    </p>
                    <p class="mt-1 pl-5 text-xl font-semibold leading-none tabular-nums text-gray-900" id="dashAttCheckOut">{!! $sk !!}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 sm:ml-auto">
                {{-- Pengingat: muncul hanya bila ada yang terlewat (lihat updateReminder()). --}}
                <div id="dashAttReminder" class="hidden items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800" role="status">
                    <i class="fas fa-bell text-xs"></i><span id="dashAttReminderText"></span>
                </div>
                {{-- Diisi dari fetch (shift aktif hari ini); tersembunyi sampai ada datanya. --}}
                <div id="dashAttShiftChip" class="hidden items-center gap-2">
                    <i class="far fa-clock text-xs text-gray-400"></i>
                    <span class="text-xs text-gray-500">Shift</span>
                    <span class="text-xs font-medium text-gray-700" id="dashAttShift"></span>
                </div>
            </div>
        </div>
    </div>
    @endif

</section>

{{-- ── COMMAND CENTER (HC-D37) ───────────────────────────────────────────
     Disisipkan di SINI, antara hero sapaan dan "Easy Access Daily" — bukan
     paling atas (terasa janggal sebelum sapaan pribadi) dan bukan pula
     setelah seluruh blok presensi (terlalu jauh ke bawah, perlu scroll).
     Posisi ini hasil percobaan langsung pemilik 1 Okt: dua posisi lain sudah
     dicoba dan ditolak. --}}
{{-- Slot: halaman induk (home) mendorong 'Company overview' ke sini agar tampil tepat di bawah header. --}}
@stack('dash-after-hero')

@include('home.command-center')

{{-- Dua kartu presensi: berdampingan mulai 2xl (≥1536 px) bila pengguna memegang keduanya; selain itu bertumpuk. --}}
<div class="grid grid-cols-1 items-start gap-6 {{ $canRecap && $canSelf ? '2xl:grid-cols-2' : '' }}">
{{-- ── SISI HR ────────────────────────────────────────────────────────── --}}
@if($canRecap)
<section aria-label="Attendance administration" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Attendance administration</h3>
            <p class="text-xs text-gray-500">Daily shortcuts and today's company-wide numbers</p>
        </div>
        <a href="{{ route('general.attendance.daily') }}" class="text-xs font-semibold primary-text hover:underline">Attendance Recap &rarr;</a>
    </div>

    {{-- Ringkasan hari ini se-perusahaan, satu baris bersekat.
         Absent SENGAJA tidak ditampilkan: selama modul Cuti belum ada, angka itu hanya dapat
         ditebak dan tebakannya menuduh karyawan yang sedang cuti sebagai alpa. --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 divide-x divide-y divide-gray-100 border-b border-gray-100 xl:divide-y-0">
        @foreach([
            ['id' => 'dashAttHrRecorded',  'label' => 'Recorded today', 'hint' => 'attendance rows'],
            ['id' => 'dashAttHrCheckedIn', 'label' => 'Checked in',     'hint' => 'employees'],
            ['id' => 'dashAttHrStillIn',   'label' => 'Still in',       'hint' => 'no check-out yet'],
            ['id' => 'dashAttHrLate',      'label' => 'Late',           'hint' => 'employees'],
        ] as $stat)
        <div class="px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
            <p class="mt-1 text-2xl font-bold leading-none tabular-nums text-gray-900" id="{{ $stat['id'] }}">{!! $sk !!}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $stat['hint'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="{{ $tilesGrid }}">
        @foreach($hrTiles as $tile)
        <a href="{{ $tile['href'] }}" class="{{ $tileClass }}">
            @if(!empty($tile['badge']))
            <span id="{{ $tile['badge'] }}" class="absolute right-3 top-3 hidden h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1.5 text-[10px] font-bold text-white"></span>
            @endif
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tile['bg'] }}">
                <i class="fas {{ $tile['icon'] }} {{ $tile['color'] }} text-sm"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold leading-tight text-gray-900">{{ $tile['title'] }}</span>
                <span class="mt-0.5 block text-xs leading-snug text-gray-500">{{ $tile['desc'] }}</span>
            </span>
        </a>
        @endforeach
    </div>
</section>
@endif

{{-- ── SISI PRIBADI ───────────────────────────────────────────────────── --}}
@if($canSelf)

<section aria-label="My attendance" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-3.5">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">My attendance</h3>
            <p class="text-xs text-gray-500">Your numbers for {{ now()->translatedFormat('F Y') }}</p>
        </div>
        <a href="{{ route('general.my-attendance.index') }}" class="text-xs font-semibold primary-text hover:underline">Open details &rarr;</a>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 divide-x divide-y divide-gray-100 border-b border-gray-100 xl:divide-y-0">
        @foreach($selfStats as $stat)
        <div class="px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $stat['label'] }}</p>
            <p class="mt-1 text-2xl font-bold leading-none tabular-nums text-gray-900" id="{{ $stat['id'] }}">{!! $sk !!}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $stat['hint'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Riwayat 7 hari dihapus atas permintaan pemilik (8 Okt): detailnya tetap ada di My Attendance. --}}
    <div class="{{ $tilesGrid }}">
        @foreach($selfTiles as $tile)
        <a href="{{ $tile['href'] }}" class="{{ $tileClass }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $tile['bg'] }}">
                <i class="fas {{ $tile['icon'] }} {{ $tile['color'] }} text-sm"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold leading-tight text-gray-900">{{ $tile['title'] }}</span>
                <span class="mt-0.5 block text-xs leading-snug text-gray-500">{{ $tile['desc'] }}</span>
            </span>
        </a>
        @endforeach
    </div>
</section>

@endif
</div>

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

    // ── Jam & progres shift — zona waktu PERUSAHAAN, bukan zona browser. Selisih jam browser vs server dikoreksi
    //    sekali (skew), jadi jam yang tampil sama dengan jam yang dipakai server menilai keterlambatan.
    const TZ = @json($tz);
    const skew = {{ now()->getTimestampMs() }} - Date.now();
    const hm = new Intl.DateTimeFormat('en-GB', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hour12: false });
    const minutesOfDay = () => {
        const p = hm.formatToParts(new Date(Date.now() + skew));
        return (parseInt(p.find(x => x.type === 'hour').value, 10) % 24) * 60 + parseInt(p.find(x => x.type === 'minute').value, 10);
    };
    let shiftRange = null, lastRecord = null;

    /**
     * Pengingat presensi (hanya bila ada rentang shift hari ini, jadi tidak muncul di hari libur/tanpa jadwal):
     *  - belum check-in dan jam kerja sudah mulai  -> "You haven't checked in yet."
     *  - sudah check-in, belum check-out, shift lewat -> "Shift has ended. Remember to check out."
     * Panel berubah kuning agar terlihat; selain itu tampil netral.
     */
    function updateReminder() {
        const box = document.getElementById('dashAttReminder'), band = document.getElementById('dashAttBand');
        if (!box || !band) return;
        let msg = '';
        if (shiftRange) {
            const now = minutesOfDay();
            if (!lastRecord || !lastRecord.check_in_at) { if (now >= shiftRange[0]) msg = "You haven't checked in yet."; }
            else if (!lastRecord.check_out_at && now >= shiftRange[1]) { msg = 'Shift has ended. Remember to check out.'; }
        }
        text('dashAttReminderText', msg);
        box.classList.toggle('hidden', !msg);
        box.classList.toggle('inline-flex', !!msg);
        band.classList.toggle('bg-amber-50/60', !!msg);
        band.classList.toggle('bg-gray-50/60', !msg);
    }

    function tick() { text('dashHeroClock', hm.format(new Date(Date.now() + skew))); updateReminder(); }
    setInterval(tick, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

    /** Jam presensi; kosong -> placeholder abu-abu "--:--" (bukan garis tebal "–"). */
    function setTime(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = value || '--:--';
        el.classList.toggle('text-gray-300', !value);
        el.classList.toggle('text-gray-900', !!value);
    }

    /** Placeholder yang masih tersisa (mis. fetch gagal / tak ada datanya) diganti "–". */
    function clearSkeletons() {
        document.querySelectorAll('.animate-pulse[aria-hidden="true"]').forEach(el => { if (el.parentNode) el.parentNode.textContent = /^dashAttCheck/.test(el.parentNode.id) ? '--:--' : '–'; });
    }

    /** Tombol utama mengikuti kondisi: belum masuk -> Check in; sudah masuk -> Check out; selesai -> lihat detail. */
    function renderCta(record) {
        const cta = document.getElementById('dashAttCta'), icon = document.getElementById('dashAttCtaIcon');
        if (!cta) return;
        let label = 'Check in now', ic = 'fa-arrow-right-to-bracket';
        if (record && record.check_in_at && !record.check_out_at) { label = 'Check out'; ic = 'fa-arrow-right-from-bracket'; }
        else if (record && record.check_in_at && record.check_out_at) { label = 'View attendance'; ic = 'fa-fingerprint'; }
        cta.textContent = label;
        icon.className = 'fas ' + ic + ' text-xs';
    }

    /** Menit -> "7 h 30 m". Sama dengan format di halaman My Attendance. */
    function duration(minutes) {
        if (!minutes || minutes <= 0) return '0 m';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return ((h > 0 ? h + ' h ' : '') + (m > 0 ? m + ' m' : '')).trim();
    }

    /** Badge status hari ini. Kuning = perlu ditinjau, BUKAN kesalahan. [label, kelas pil, kelas titik] */
    function todayBadge(record) {
        if (!record || !record.check_in_at) return ['Not checked in', 'bg-gray-100 text-gray-600', 'bg-gray-400'];
        if (!record.check_out_at)            return ['Checked in',     'bg-blue-50 text-blue-700', 'bg-blue-500'];
        if (record.late_minutes > 0)         return ['Completed, late ' + record.late_minutes + ' m', 'bg-amber-50 text-amber-700', 'bg-amber-500'];
        return ['Completed', 'bg-emerald-50 text-emerald-700', 'bg-emerald-500'];
    }

    function renderSelf(self) {
        if (!self) return;

        const record = self.record;
        setTime('dashAttCheckIn',  record && record.check_in_at);
        setTime('dashAttCheckOut', record && record.check_out_at);

        const badge = document.getElementById('dashAttBadge');
        if (badge) {
            const [label, cls, dot] = todayBadge(record);
            badge.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ' + cls;
            badge.innerHTML = '<span class="h-1.5 w-1.5 rounded-full ' + dot + '"></span><span></span>';
            badge.lastChild.textContent = label;
        }

        renderCta(record);
        lastRecord = record || null;

        if (self.shift) {
            const m = /(\d{2}):(\d{2})\s*-\s*(\d{2}):(\d{2})/.exec(self.shift.time_range || '');
            // Shift lintas tengah malam (akhir <= awal) tidak diberi pengingat: perhitungannya perlu tanggal, bukan jam saja.
            if (m && (+m[3] * 60 + +m[4]) > (+m[1] * 60 + +m[2])) { shiftRange = [+m[1] * 60 + +m[2], +m[3] * 60 + +m[4]]; }
            text('dashAttShift', self.shift.name + ' (' + self.shift.time_range + ')');
            const chip = document.getElementById('dashAttShiftChip');
            if (chip) { chip.classList.remove('hidden'); chip.classList.add('inline-flex'); }
        }

        updateReminder();

        const s = self.summary || {};
        text('dashAttPresent',  s.present ?? 0);
        text('dashAttLate',     s.late ?? 0);
        text('dashAttWork',     duration(s.work_minutes));
        text('dashAttOvertime', duration(s.overtime_minutes));
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
        })
        .finally(clearSkeletons);
})();
</script>
@endpush
@endif

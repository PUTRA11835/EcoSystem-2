<aside id="sidebar"
    class="sidebar-transition fixed inset-y-0 left-0 h-screen flex flex-col overflow-hidden {{ $preferences['sidebar_style'] === 'gradient' ? 'primary-gradient' : 'primary-solid' }} text-white shadow-2xl z-50 w-64 -translate-x-full lg:translate-x-0">
    @php
        // HC-D25 — Favorit hanya untuk karyawan (portal pelanggan tidak memakainya).
        // forEmployee() tidak pernah melempar galat: sidebar tampil di SETIAP halaman.
        $sbFavoritesEnabled = (session('user.type') ?? null) === 'employee';
        $sbFavorites = $sbFavoritesEnabled
            ? app(\App\Services\Sidebar\SidebarFavoriteService::class)->forEmployee((int) session('user.id'))
            : [];
    @endphp
    <script>
        window.__sidebarFavoritesEnabled = @json($sbFavoritesEnabled);
        window.__sidebarFavorites = @json($sbFavorites);
        window.__sidebarFavoritesMax = {{ \App\Services\Sidebar\SidebarFavoriteService::MAX }};
    </script>

    {{-- HC: header TETAP (logo + pencarian menu). Dulu seluruh <aside> yang di-scroll
         (overflow-y-auto) sehingga logo ikut terguling. Kini <aside> berupa kolom flex:
         header `flex-shrink-0` tidak pernah bergeser, hanya #sidebarScroll di bawahnya
         yang menggulung. --}}
    <div id="sidebarHeader" class="flex-shrink-0 px-4 pt-4 pb-3">
        <!-- Logo Section -->
        <div class="sidebar-logo flex items-center justify-center">
            <div class="w-full rounded-xl px-3 backdrop-blur-sm">
                <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem Logo" class="w-full h-auto mx-auto" style="max-height:64px; object-fit:contain;" />
            </div>
        </div>

        <!-- Pencarian menu -->
        <div class="relative mt-3">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white text-opacity-60 pointer-events-none"></i>
            <input id="sidebarSearch" type="search" autocomplete="off" spellcheck="false"
                placeholder="Search menu..."
                aria-label="Search menu"
                class="w-full pl-9 pr-8 py-2 rounded-lg text-sm text-white placeholder-white placeholder-opacity-60 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-40">
            <button type="button" id="sidebarSearchClear" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-white text-opacity-70 hover:text-opacity-100 text-xs" aria-label="Clear search">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- HC-D25 — FAVORIT. Di header TETAP agar selalu terlihat; disembunyikan bila kosong.
             Isinya SALINAN tautan yang sudah ada di sidebar (sudah disaring izin di server),
             sehingga favorit tak bisa membuka halaman yang izinnya sudah dicabut. --}}
        <div id="sidebarFavorites" class="hidden mt-3">
            <button type="button" id="sidebarFavToggle" class="w-full flex items-center justify-between px-1 pb-1 text-left" aria-expanded="true" aria-controls="sidebarFavList">
                <span class="sb-label-inline">Favorites</span>
                <i class="fas fa-chevron-down text-[10px] text-white text-opacity-60 transition-transform" id="sidebarFavChevron"></i>
            </button>
            <div id="sidebarFavList" class="space-y-1"></div>
        </div>
    </div>

    <!-- Navigation Menu (satu-satunya bagian yang di-scroll) -->
    <div id="sidebarScroll" class="flex-1 min-h-0 overflow-y-auto">
    @hasSection('sidebar-nav')
        @yield('sidebar-nav')
    @else
        <nav class="py-4 px-3 space-y-1">
            @php
                $essConfig    = \App\Http\Controllers\Management\EssSettingsController::getEssSettings();
                $essGroupData = \App\Http\Controllers\Management\EssSettingsController::getEssGroups();

                // 🔴 D182 — SATU array data menggantikan lima belas blok
                // `@if(!empty($essConfig[...]))` yang dulu ditulis tangan satu
                // per satu. Setiap baris di sini adalah PERSIS logika yang
                // sudah ada sebelumnya (route, pola aktif, gerbang tambahan) —
                // dipindah ke bentuk data, bukan ditulis ulang — supaya
                // perilaku untuk siapa pun yang TIDAK memakai fitur grup baru
                // (mis. instalasi yang belum pernah membuat grup) IDENTIK
                // dengan sebelum D182.
                //
                // `logout`, `events_calendar`, `my_timesheet` SENGAJA tidak
                // ada di sini — lihat EssSettingsController::UNGROUPABLE_ITEMS
                // untuk alasannya (logout tidak pernah benar-benar dijaga
                // sakelar ini; dua lainnya sudah punya dropdown "Calendar"
                // sendiri di bawah, di luar cakupan fitur ini).
                $essNav = [
                    'home' => [
                        'label'   => 'Home',
                        'icon'    => 'fas fa-home',
                        'href'    => route('dashboard'),
                        'active'  => Request::is('dashboard'),
                        'visible' => !empty($essConfig['home']),
                    ],
                    'my_profile' => [
                        'label'   => 'My Profile',
                        'icon'    => 'fas fa-user-circle',
                        'href'    => route('profile.my'),
                        'active'  => Request::is('my-profile*') || Request::is('profile*'),
                        'visible' => !empty($essConfig['my_profile']),
                    ],
                    'my_attendance' => [
                        'label'   => 'My Attendance',
                        'icon'    => 'fas fa-user-clock',
                        'href'    => route('general.my-attendance.index'),
                        'active'  => Request::is('general/my-attendance*'),
                        'visible' => !empty($essConfig['my_attendance']),
                    ],
                    'my_leave_permit' => [
                        'label'   => 'My Leave & Permit',
                        'icon'    => 'fas fa-calendar-check',
                        'href'    => route('my-leave-permit'),
                        'active'  => Request::is('my-leave-permit*'),
                        'visible' => !empty($essConfig['my_leave_permit']),
                    ],
                    'overtime' => [
                        'label'   => 'Overtime',
                        'icon'    => 'fas fa-business-time',
                        'href'    => route('general.my-overtime.index'),
                        'active'  => Request::is('general/my-overtime*'),
                        'visible' => !empty($essConfig['overtime']),
                    ],
                    'expense_reimbursement' => [
                        'label'   => 'My Reimbursement',
                        'icon'    => 'fas fa-receipt',
                        'href'    => route('general.my-reimbursement.index'),
                        'active'  => Request::is('general/my-reimbursement*'),
                        'visible' => !empty($essConfig['expense_reimbursement']),
                    ],
                    // route('coming-soon', ...) — belum menunjuk halaman sungguhan.
                    'paystub' => [
                        'label'   => 'Paystub',
                        'icon'    => 'fas fa-file-invoice-dollar',
                        'href'    => route('coming-soon', ['feature' => 'Paystub']),
                        'active'  => false,
                        'visible' => !empty($essConfig['paystub']),
                    ],
                    'purchase_request' => [
                        'label'   => 'Purchase Request',
                        'icon'    => 'fas fa-shopping-cart',
                        'href'    => route('general.my-purchase-request.index'),
                        'active'  => Request::is('general/my-purchase-request*'),
                        'visible' => !empty($essConfig['purchase_request']),
                    ],
                    // 🔴 Pola aktif PRESISI, bukan wildcard (Keputusan D161) — supaya
                    // tidak ikut menangkap `my-cash-advance-report` dan menyalakan
                    // dua item sekaligus.
                    'advance_payment_ca' => [
                        'label'   => 'Cash Advance',
                        'icon'    => 'fas fa-hand-holding-usd',
                        'href'    => route('general.my-cash-advance.index'),
                        'active'  => Request::is('general/my-cash-advance') || Request::is('general/my-cash-advance/*'),
                        'visible' => !empty($essConfig['advance_payment_ca']),
                    ],
                    'advance_payment_car' => [
                        'label'   => 'Cash Advance Report',
                        'icon'    => 'fas fa-file-contract',
                        'href'    => route('general.my-cash-advance-report.index'),
                        'active'  => Request::is('general/my-cash-advance-report*'),
                        'visible' => !empty($essConfig['advance_payment_car']),
                    ],
                    // route('coming-soon', ...) — belum menunjuk halaman sungguhan.
                    'loans' => [
                        'label'   => 'My Loans',
                        'icon'    => 'fas fa-landmark',
                        'href'    => route('coming-soon', ['feature' => 'Loans']),
                        'active'  => false,
                        'visible' => !empty($essConfig['loans']),
                    ],
                    // Gerbang GANDA: sakelar ESS DAN slug izin (role User System Registered).
                    'my_letter_requests' => [
                        'label'   => 'My Letter Requests',
                        'icon'    => 'fas fa-envelope-open-text',
                        'href'    => route('general.my-letter-requests.index'),
                        'active'  => Request::is('general/my-letter-requests*'),
                        'visible' => !empty($essConfig['my_letter_requests']) && $can('general.my-letter-requests'),
                    ],
                    'my_kpis' => [
                        'label'   => 'My KPI',
                        'icon'    => 'fas fa-chart-line',
                        'href'    => route('general.my-kpi.index'),
                        'active'  => Request::is('general/my-kpi*'),
                        'visible' => !empty($essConfig['my_kpis']),
                    ],
                    // Gerbang GANDA seperti sebelumnya: sakelar ESS DAN slug izin.
                    'ai_assistant' => [
                        'label'   => 'AI Assistant',
                        'icon'    => 'fas fa-robot',
                        'href'    => route('ai-assistant'),
                        'active'  => Request::is('ai-assistant*'),
                        'visible' => !empty($essConfig['ai_assistant']) && $can('ai-assistant'),
                    ],
                    'ai_research' => [
                        'label'   => 'AI Research',
                        'icon'    => 'fas fa-magnifying-glass-chart',
                        'href'    => route('ai-research'),
                        'active'  => Request::is('ai-research*'),
                        'visible' => !empty($essConfig['ai_research']) && $can('ai-research'),
                    ],
                ];

                // Susun urutan tampil: item flat dan grup diselingi mengikuti
                // urutan ASLI $essNav di atas — grup muncul persis di posisi
                // anggota PERTAMANYA. Tanpa satu pun grup terkonfigurasi
                // (instalasi baru, atau sebelum D182), ini menghasilkan urutan
                // yang identik dengan lima belas blok lama.
                $essRenderedGroupIds = [];
                $essOutput = [];

                foreach ($essNav as $essKey => $essItem) {
                    $essGroupId = $essGroupData['assignments'][$essKey] ?? null;

                    if ($essGroupId === null) {
                        $essOutput[] = ['type' => 'item', 'item' => $essItem];
                        continue;
                    }

                    if (in_array($essGroupId, $essRenderedGroupIds, true)) {
                        continue; // sudah dirender lewat kemunculan pertama grup ini
                    }

                    $essGroup = collect($essGroupData['groups'])->firstWhere('id', $essGroupId);

                    if (!$essGroup) {
                        // Seharusnya sudah disaring getEssGroups() — jaga-jaga saja,
                        // supaya item tidak pernah hilang hanya karena grupnya cacat.
                        $essOutput[] = ['type' => 'item', 'item' => $essItem];
                        continue;
                    }

                    $essMembers = collect($essNav)
                        ->filter(fn ($it, $k) => ($essGroupData['assignments'][$k] ?? null) === $essGroupId)
                        ->values();

                    $essOutput[] = ['type' => 'group', 'group' => $essGroup, 'members' => $essMembers];
                    $essRenderedGroupIds[] = $essGroupId;
                }
            @endphp

            @foreach($essOutput as $essEntry)
                @if($essEntry['type'] === 'item')
                    @if($essEntry['item']['visible'])
                        @include('partials.ess-nav-item', [
                            'href'   => $essEntry['item']['href'],
                            'icon'   => $essEntry['item']['icon'],
                            'label'  => $essEntry['item']['label'],
                            'active' => $essEntry['item']['active'],
                            'nested' => false,
                        ])
                    @endif
                @else
                    @php
                        $essVisibleMembers = $essEntry['members']->filter(fn ($m) => $m['visible'])->values();
                        $essGroupActive    = $essVisibleMembers->contains('active', true);
                    @endphp
                    @if($essVisibleMembers->count() > 0)
                        <div class="mb-2">
                            <button onclick="toggleEssGroupDropdown('{{ $essEntry['group']['id'] }}')"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $essGroupActive ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="{{ $essEntry['group']['icon'] }}"></i>
                                </span>
                                <span class="nav-text flex-1 font-medium">{{ $essEntry['group']['label'] }}</span>
                                <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $essGroupActive ? 'rotate-180' : '' }}" id="essGroup{{ $essEntry['group']['id'] }}Chevron"></i>
                            </button>
                            <div id="essGroup{{ $essEntry['group']['id'] }}Dropdown"
                                class="nav-text {{ $essGroupActive ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                                @foreach($essVisibleMembers as $essMember)
                                    @include('partials.ess-nav-item', [
                                        'href'   => $essMember['href'],
                                        'icon'   => $essMember['icon'],
                                        'label'  => $essMember['label'],
                                        'active' => $essMember['active'],
                                        'nested' => true,
                                    ])
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            @endforeach

            @php
                $showEvents = !empty($essConfig['events_calendar']) && ($can('calendar.events') || Auth::check());
                $showTimesheets = !empty($essConfig['my_timesheet']) && ($can('calendar.timesheets') || Auth::check());
                $showCalendarMenu = ($can('calendar') || Auth::check()) && ($showEvents || $showTimesheets);
            @endphp

            @if($showCalendarMenu)
                <!-- CALENDAR Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleCalendarDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('calendar*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Calendar</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="calendarChevron"></i>
                    </button>
                    <div id="calendarDropdown"
                        class="nav-text {{ Request::is('calendar*') ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                        @if($showEvents)
                            <a href="{{ route('calendar.events') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('calendar/events*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-calendar-check text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Events</span>
                            </a>
                        @endif
                        @if($showTimesheets)
                            <a href="{{ route('calendar.timesheets') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('calendar/timesheets*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-clock text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Timesheets</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif


            {{-- Dropdown Reporting versi lengkap — memuat sub-grup Project & Support beserta Consultant Assignment dan Diagram Report. Fungsi _toggleReportingGroup() yang menggerakkannya ada di dashboard.blade.php. --}}
            @if($can('reporting'))
            <!-- REPORTING Dropdown -->
            <div class="mb-2">
                <button onclick="toggleReportingDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('reporting*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                    <span class="nav-icon w-5 h-5 flex items-center justify-center">
                        <i class="fas fa-chart-line"></i>
                    </span>
                    <span class="nav-text flex-1 font-medium">Reporting</span>
                    <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="reportingChevron"></i>
                </button>
                @php
                    // Reporting dipisah jadi dua grup: Project & Support. Grup hanya
                    // dirender kalau user punya minimal satu laporan di dalamnya —
                    // tidak ada slug izin baru, murni pengelompokan visual.
                    // Catatan: 'reporting/collection-outlook*' TIDAK dipakai untuk grup
                    // Project karena wildcard-nya ikut menangkap collection-outlook-support.
                    $repCoProject     = Request::is('reporting/collection-outlook') || Request::is('reporting/collection-outlook/*');
                    $repProjectActive = $repCoProject || Request::is('reporting/consultant-assignment*');
                    $repSupportActive = Request::is('reporting')
                        || Request::is('reporting/md-recap*')
                        || Request::is('reporting/collection-outlook-support*')
                        || Request::is('reporting/ticketing-overview*')
                        || Request::is('reporting/ticket-by-module*')
                        || Request::is('reporting/log-shifting*')
                        || Request::is('reporting/resolution-days*');
                    $canRepProject = $can('reporting.collection-outlook') || $can('reporting.consultant-assignment');
                    $canRepSupport = $can('reporting.validation')
                        || $can('reporting.md-recap')
                        || $can('reporting.collection-outlook-support')
                        || $can('reporting.ticketing-overview')
                        || $can('reporting.ticket-by-module')
                        || $can('reporting.log-shifting')
                        || $can('reporting.resolution-days');
                @endphp
                <div id="reportingDropdown" class="nav-text {{ Request::is('reporting*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                    @if($canRepProject)
                    {{-- Reporting → Project --}}
                    <div>
                        <button onclick="toggleReportingProjectDropdown()" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ $repProjectActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-project-diagram text-xs"></i>
                            </span>
                            <span class="nav-text text-sm flex-1">Project</span>
                            <i class="fas fa-chevron-down text-[10px] nav-text transition-transform {{ $repProjectActive ? 'rotate-180' : '' }}" id="reportingProjectChevron"></i>
                        </button>
                        <div id="reportingProjectDropdown" class="nav-text {{ $repProjectActive ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                            @if($can('reporting.collection-outlook'))
                            <a href="{{ route('reporting.collection-outlook') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ $repCoProject ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-hand-holding-usd text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Collection Outlook</span>
                            </a>
                            @endif
                            @if($can('reporting.consultant-assignment'))
                            <a href="{{ route('reporting.consultant-assignment') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('reporting/consultant-assignment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-users text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Consultant Assignment</span>
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif
                    @if($canRepSupport)
                    {{-- Reporting → Support --}}
                    <div>
                        <button onclick="toggleReportingSupportDropdown()" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ $repSupportActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-headset text-xs"></i>
                            </span>
                            <span class="nav-text text-sm flex-1">Support</span>
                            <i class="fas fa-chevron-down text-[10px] nav-text transition-transform {{ $repSupportActive ? 'rotate-180' : '' }}" id="reportingSupportChevron"></i>
                        </button>
                        <div id="reportingSupportDropdown" class="nav-text {{ $repSupportActive ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                            @if($can('reporting.validation'))
                            <a href="{{ route('reporting') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('reporting') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-check-circle text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">MD Validation</span>
                            </a>
                        @endif
                        @if($can('reporting.md-recap'))
                            <a href="{{ route('reporting.md-recap') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/md-recap*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-table text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">MD Recap</span>
                            </a>
                        @endif
                        @if($can('reporting.collection-outlook'))
                            <a href="{{ route('reporting.collection-outlook') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ (Request::is('reporting/collection-outlook') || Request::is('reporting/collection-outlook/*')) ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-hand-holding-usd text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Collection Outlook</span>
                            </a>
                        @endif
                        @if($can('reporting.collection-outlook-support'))
                            <a href="{{ route('reporting.collection-outlook-support') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/collection-outlook-support*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-hand-holding-usd text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Collection Outlook (Support)</span>
                            </a>
                        @endif
                        @if($can('reporting.ticketing-overview'))
                            <a href="{{ route('reporting.ticketing-overview') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/ticketing-overview*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-headset text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Ticketing Overview</span>
                            </a>
                        @endif
                        @if($can('reporting.ticket-by-module'))
                            <a href="{{ route('reporting.ticket-by-module') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/ticket-by-module*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-puzzle-piece text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Ticket by Modul</span>
                            </a>
                        @endif
                        @if($can('reporting.log-shifting'))
                            <a href="{{ route('reporting.log-shifting') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/log-shifting*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-clock text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Log Shifting</span>
                            </a>
                        @endif
                        @if($can('reporting.resolution-days'))
                            <a href="{{ route('reporting.resolution-days') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/resolution-days*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-hourglass-half text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Resolution Days</span>
                            </a>
                            @endif
                        </div>
                    </div>
                    @endif
                    @if($can('reporting.diagram-report'))
                    <a href="{{ route('reporting.diagram-report') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/diagram-report*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-4 h-4 flex items-center justify-center">
                            <i class="fas fa-chart-pie text-xs"></i>
                        </span>
                        <span class="nav-text text-sm">Diagram Report</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            @if($can('master'))
                <!-- MASTER Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleMasterDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('master*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-database"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Master</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="masterChevron"></i>
                    </button>
                    <div id="masterDropdown"
                        class="nav-text {{ Request::is('master*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('master.employee'))
                            <a href="{{ route('master.employee.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-users text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Employee</span>
                            </a>
                        @endif
                        @if($can('master.customer'))
                            <a href="{{ route('master.customer.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/customer*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-user-tie text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Business Partner</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- HC-D18 — Grup "HUMAN CAPITAL" seperti pada aplikasi acuan (ESH). Label seksi +
                 item yang SUDAH ada saja; item lain (Struktur Organisasi, Rekrutmen, Offering
                 Letter, Kontrak, Template Kontrak, Offboarding) ditambahkan di sini saat
                 modulnya dibangun — tidak ada tautan mati. Master → Employee TETAP ada
                 (prinsip HC-D15: hanya penambahan). Gerbang tiap item = slug-nya sendiri,
                 tanpa `|| $can('general')`, agar item tak muncul hanya karena memegang induk. --}}
            @if($can('master.employee') || $can('general.onboarding'))
                <div class="sb-label nav-text">Human Capital</div>
                @if($can('master.employee'))
                    <a href="{{ route('master.employee.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users text-sm"></i>
                        </span>
                        <span class="nav-text text-sm">Employee Data</span>
                    </a>
                @endif
                @if($can('general.onboarding'))
                    <a href="{{ route('general.onboarding.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/onboarding*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-user-check text-sm"></i>
                        </span>
                        <span class="nav-text text-sm">Onboarding</span>
                    </a>
                @endif
            @endif

            @if($can('financial'))
                <!-- FINANCIAL -->
                <div class="mb-2">
                    <a href="#"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('financial') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-coins"></i>
                        </span>
                        <span class="nav-text font-medium">Financial</span>
                    </a>
                </div>
            @endif

            {{-- 🔴 D175: Branches/Shifts/Attendance Settings/Overtime Settings
                 pindah jadi tab DI DALAM dropdown ini (bukan lagi hidup di
                 dropdown Management terpisah yang punya gerbangnya sendiri).
                 Keempat slug itu — plus `general.attendance.monthly` yang
                 sebelumnya juga terlewat — WAJIB ada di gerbang terluar ini.
                 Tanpanya, orang yang HANYA memegang mis. `general.settings.
                 branches` kehilangan SATU-SATUNYA jalan menuju Branches:
                 dropdown-nya sendiri tidak pernah dirender. Ditemukan lewat
                 uji nyata (render sidebar dengan satu slug terisolasi), bukan
                 dugaan — lihat smoke-hub-tabs.php. --}}
            {{-- 🔴 D177: Reimbursement/Purchase Request/Cash Advance Settings ikut
                 masuk gerbang ini — kelas cacat yang sama dengan D175, kali ini
                 dicegah dari awal alih-alih ditemukan lewat uji. --}}
            {{-- 🔴 D180: kelima slug Approval Workflow ikut masuk gerbang ini juga,
                 dengan alasan yang SAMA PERSIS — tanpanya, orang yang HANYA
                 memegang mis. `general.approval-workflow.overtime` (dan tidak
                 memegang slug HR & General lain apa pun) tidak akan pernah
                 melihat dropdown-nya sama sekali. --}}
            @if($can('general') || $can('hr_general.leave_permit.admin')
                || $can('general.attendance') || $can('general.attendance.monthly') || $can('general.attendance.correction')
                || $can('general.settings.branches') || $can('general.settings.shifts') || $can('general.settings.attendance')
                || $can('general.overtime') || $can('general.settings.overtime')
                || $can('general.reimbursement') || $can('general.settings.reimbursement')
                || $can('general.purchase-request') || $can('general.settings.purchase-request')
                || $can('general.cash-advance') || $can('management.cash-advance-settings')
                || $can('general.cash-advance-report')
                || $can('general.approval-workflow.overtime') || $can('general.approval-workflow.reimbursement')
                || $can('general.approval-workflow.purchase-request') || $can('management.approval-workflow.cash-advance')
                || $can('management.approval-workflow.cash-advance-report')
                || $can('general.recruitment') || $can('general.recruitment.jobs') || $can('general.recruitment.candidates')
                || $can('general.recruitment.schedule') || $can('general.recruitment.offers') || $can('general.recruitment.settings')
                || $can('general.recruitment.offers.settings') || $can('general.letter-templates')
                || $can('general.letters.dashboard') || $can('general.letters.requests') || $can('general.letters.register') || $can('general.letters.compose'))
                <!-- HR & GENERAL -->
                @php
                    // 🔴 Daftar ini harus diperbarui setiap kali item baru masuk ke grup —
                    // kelalaian yang sempat terjadi pada Cash Advance: itemnya menyala di
                    // dalam grup, tetapi grupnya sendiri tetap TERLIPAT saat halamannya
                    // dibuka. Sidebar yang ditulis tangan selalu punya dua daftar yang
                    // harus dijaga sejalan: siapa boleh melihat, dan kapan grup terbuka.
                    $hrGeneralOpen = Request::is('hr-general*')
                        || Request::is('general/attendance*')
                        || Request::is('general/overtime*')
                        || Request::is('general/reimbursement*')
                        || Request::is('general/purchase-request*')
                        || Request::is('general/cash-advance*')
                        // 🔴 D177 — Cash Advance Settings TETAP di URL lama
                        // (management/cash-advance-settings*, lihat routes/hr-general.php),
                        // tetapi kini tab di hub "Cash Advance (CA)". Tanpa baris ini,
                        // membuka tab Settings membuat dropdown "HR & General" tertutup
                        // sendiri padahal baris "Cash Advance (CA)" ikut menyala.
                        || Request::is('management/cash-advance-settings*')
                        || Request::is('general/kpi-evaluation*')
                        // 🔴 D180 — hub Approval Workflow, satu prefix untuk kelima tab.
                        || Request::is('general/approval-workflow*')
                        || Request::is('general/recruitment*')
                        || Request::is('general/letters*');
                @endphp
                <div class="mb-2">
                    <button onclick="toggleHrGeneralDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $hrGeneralOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users-cog"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">HR & General</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $hrGeneralOpen ? 'rotate-180' : '' }}"
                            id="hrGeneralChevron"></i>
                    </button>
                    <div id="hrGeneralDropdown"
                        class="nav-text {{ $hrGeneralOpen ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('hr_general.leave_permit.admin') || $can('general'))
                            <a href="{{ route('hr-general.leave-permit') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('hr-general/leave-permit*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-calendar-minus text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Leave & Permit</span>
                            </a>
                        @endif

                        {{-- 🔴 SATU baris untuk hub Attendance (D175) — dulunya DUA
                             ("Attendance Recap" + "Attendance Corrections"), plus TIGA
                             baris lagi di Management → HR & General (Branches, Shifts,
                             Attendance Settings). Keenamnya kini tab di dalam satu hub;
                             sidebar hanya perlu satu pintu masuk.

                             Gerbangnya "ATAU" atas SELURUH enam slug — bukan cuma
                             `general.attendance` — supaya orang yang HANYA memegang
                             mis. Branches (tanpa Daily Recap) tetap melihat baris ini.
                             Landasannya tab PERTAMA yang benar-benar ia pegang, mengikuti
                             urutan yang sama dengan tab bar (hr-general/attendance/hub-tabs-attendance),
                             sehingga tidak pernah melempar ke halaman yang menolaknya
                             (pola yang sama dengan D172). --}}
                        @php
                            $attendanceGate = $can('general.attendance')
                                || $can('general.attendance.monthly')
                                || $can('general.attendance.correction')
                                || $can('general.settings.branches')
                                || $can('general.settings.shifts')
                                || $can('general.settings.attendance')
                                || $can('general');

                            $attendanceLanding = match (true) {
                                $can('general.attendance') || $can('general')            => route('general.attendance.daily'),
                                $can('general.attendance.monthly')                       => route('general.attendance.monthly'),
                                $can('general.attendance.correction')                    => route('general.attendance.corrections.index'),
                                $can('general.settings.branches')                        => route('general.attendance.branches.index'),
                                $can('general.settings.shifts')                          => route('general.attendance.shifts.index'),
                                $can('general.settings.attendance')                      => route('general.attendance.settings.edit'),
                                default                                                  => route('general.attendance.daily'),
                            };
                        @endphp
                        @if($attendanceGate)
                            <a href="{{ $attendanceLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/attendance*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-clipboard-list text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Attendance</span>
                            </a>
                        @endif

                        {{-- 🔴 Label DISAMAKAN dengan `menu.name` (D174). Gerbang &
                             landasannya DILEBARKAN untuk D175: dulu hanya
                             `general.overtime` yang membuka baris ini, sehingga
                             seseorang yang HANYA memegang Overtime Settings (kini tab
                             kedua di hub yang sama) tidak akan melihat baris ini sama
                             sekali — jalan satu-satunya menuju Settings hilang. --}}
                        @php
                            $overtimeGate = $can('general.overtime') || $can('general.settings.overtime') || $can('general');
                            $overtimeLanding = ($can('general.overtime') || $can('general'))
                                ? route('general.overtime.index')
                                : route('general.overtime.settings.edit');
                        @endphp
                        @if($overtimeGate)
                            <a href="{{ $overtimeLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/overtime*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-clock text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Overtime Management</span>
                            </a>
                        @endif


                        {{-- 🔴 D177 — Gerbang & landasan DILEBARKAN, pola sama dengan
                             Overtime di atas: Reimbursement Settings kini tab kedua di
                             hub yang sama, jadi orang yang HANYA memegang slug
                             setelannya tetap perlu jalan masuk lewat baris ini. --}}
                        @php
                            $reimbursementGate = $can('general.reimbursement') || $can('general.settings.reimbursement') || $can('general');
                            $reimbursementLanding = ($can('general.reimbursement') || $can('general'))
                                ? route('general.reimbursement.index')
                                : route('general.reimbursement.settings.edit');
                        @endphp
                        @if($reimbursementGate)
                            <a href="{{ $reimbursementLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/reimbursement*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-receipt text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Reimbursement Management</span>
                            </a>
                        @endif

                        {{-- 🔴 D177 — sama seperti Reimbursement di atas. --}}
                        @php
                            $purchaseRequestGate = $can('general.purchase-request') || $can('general.settings.purchase-request') || $can('general');
                            $purchaseRequestLanding = ($can('general.purchase-request') || $can('general'))
                                ? route('general.purchase-request.index')
                                : route('general.purchase-request.settings.edit');
                        @endphp
                        @if($purchaseRequestGate)
                            <a href="{{ $purchaseRequestLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/purchase-request*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-cart-shopping text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Purchase Request Management</span>
                            </a>
                        @endif

                        {{-- 🔴 Namanya memakai singkatan — "Cash Advance (CA)", bukan
                             "Cash Advance" polos. Itulah PEMBEDA sisi admin dari item
                             ESS bernama sama (Keputusan D142/D151); tanpanya, dua baris
                             identik di layar Menu Access membuat pembagian izin jadi
                             tebak-tebakan.

                             🔴 D177 — Gerbang & landasan DILEBARKAN dengan
                             `management.cash-advance-settings`: halaman itu kini tab
                             kedua di hub ini (lihat routes/hr-general.php, blok Cash
                             Advance Settings), meski URL & slug-nya TETAP di luar
                             `general.*` (D141) — hanya tampilannya yang digabung. --}}
                        @if($can('general.cash-advance') || $can('management.cash-advance-settings') || $can('general'))
                            {{-- 🔴 Presisi, bukan `cash-advance*` (D161) — cacat yang
                                 sama dengan sisi ESS: wildcard itu ikut menangkap
                                 `cash-advance-report` dan menyalakan dua item. --}}
                            @php
                                $hrCaActive = Request::is('general/cash-advance')
                                    || Request::is('general/cash-advance/*')
                                    || Request::is('management/cash-advance-settings*');
                                $cashAdvanceLanding = ($can('general.cash-advance') || $can('general'))
                                    ? route('general.cash-advance.index')
                                    : route('management.cash-advance-settings.edit');
                            @endphp
                            <a href="{{ $cashAdvanceLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ $hrCaActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-hand-holding-usd text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Cash Advance (CA)</span>
                            </a>
                        @endif

                        {{-- 🔴 D179 — CAR TIDAK punya hub tab (D177 sengaja membiarkannya
                             terpisah dari Cash Advance), jadi badge "menunggu saya"-nya
                             ditaruh di baris sidebar ini langsung, bukan di sebuah tab.
                             Dihitung HANYA saat gerbangnya lolos — pengguna yang tidak
                             berhak atas baris ini tidak pernah memicu query-nya. --}}
                        @if($can('general.cash-advance-report') || $can('general'))
                            @php
                                $carPending = count(app(\App\Services\CashAdvance\CashAdvanceReportService::class)
                                    ->pendingIdsFor((int) session('user.id')));
                            @endphp
                            <a href="{{ route('general.cash-advance-report.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/cash-advance-report*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-file-invoice-dollar text-xs"></i>
                                </span>
                                <span class="nav-text text-sm flex-1">Cash Advance Report (CAR)</span>
                                @if($carPending > 0)
                                    <span class="nav-text bg-yellow-100 text-yellow-800 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        {{ $carPending > 99 ? '99+' : $carPending }}
                                    </span>
                                @endif
                            </a>
                        @endif

                        @if($can('general.kpi-evaluation') || $can('general'))
                            <a href="{{ route('general.kpi-evaluation.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/kpi-evaluation*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">KPI Evaluation</span>
                            </a>
                        @endif

                        {{-- 🔴 D180 — "Approval Workflow", di BAWAH grup ini (posisi
                             disepakati pemilik sistem: konfigurasi berkala, bukan
                             operasional harian). Gerbangnya SENGAJA TIDAK memakai
                             `|| $can('general')` — kelima slug barunya (lihat migrasi
                             2026_09_16_000001_add_approval_workflow_menus) memang
                             dibuat supaya HANYA terlihat lewat pemberian manual,
                             "pengawasan ketat" yang diminta secara eksplisit. Seorang
                             pemegang `general` blanket TIDAK otomatis melihat baris
                             ini — beda dari hampir seluruh baris lain di dropdown ini. --}}
                        @php
                            $approvalWorkflowGate = $can('general.approval-workflow.overtime')
                                || $can('general.approval-workflow.reimbursement')
                                || $can('general.approval-workflow.purchase-request')
                                || $can('management.approval-workflow.cash-advance')
                                || $can('management.approval-workflow.cash-advance-report');

                            $approvalWorkflowLanding = match (true) {
                                $can('general.approval-workflow.overtime')            => route('general.approval-workflow.overtime'),
                                $can('general.approval-workflow.reimbursement')       => route('general.approval-workflow.reimbursement'),
                                $can('general.approval-workflow.purchase-request')    => route('general.approval-workflow.purchase-request'),
                                $can('management.approval-workflow.cash-advance')     => route('general.approval-workflow.cash-advance'),
                                $can('management.approval-workflow.cash-advance-report') => route('general.approval-workflow.cash-advance-report'),
                                default => route('general.approval-workflow.overtime'),
                            };
                        @endphp
                        @if($approvalWorkflowGate)
                            <a href="{{ $approvalWorkflowLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/approval-workflow*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-list-check text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Approval Workflow</span>
                            </a>
                        @endif

                        {{-- Rekrutmen — hub bertab (Dashboard / Selection Process / Schedule /
                             Job Openings / Settings), mengikuti pola Attendance & Overtime:
                             gerbangnya ATAU atas seluruh tab, landasannya tab PERTAMA
                             yang benar-benar dipegang. --}}
                        @php
                            // Offers is intentionally EXCLUDED here — it is its own sidebar
                            // entry below, not a tab inside the Recruitment hub, so this
                            // gate/landing/active-check must stay in sync with the five tabs
                            // actually listed in hr-general/recruitment/components/hub-tabs.blade.php.
                            $recruitmentGate = $can('general.recruitment') || $can('general.recruitment.jobs')
                                || $can('general.recruitment.candidates') || $can('general.recruitment.schedule')
                                || $can('general.recruitment.settings');

                            $recruitmentLanding = match (true) {
                                $can('general.recruitment')           => route('general.recruitment.index'),
                                $can('general.recruitment.candidates') => route('general.recruitment.candidates.index'),
                                $can('general.recruitment.schedule')  => route('general.recruitment.schedule.index'),
                                $can('general.recruitment.jobs')      => route('general.recruitment.jobs.index'),
                                $can('general.recruitment.settings')  => route('general.recruitment.settings.edit'),
                                default => route('general.recruitment.index'),
                            };

                            $recruitmentActive = Request::is('general/recruitment')
                                || Request::is('general/recruitment/jobs*')
                                || Request::is('general/recruitment/candidates*')
                                || Request::is('general/recruitment/schedule*')
                                || Request::is('general/recruitment/settings*');
                        @endphp
                        @if($recruitmentGate)
                            <a href="{{ $recruitmentLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ $recruitmentActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-user-tie text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Recruitment</span>
                            </a>
                        @endif

                        {{-- Offering Letter — a standalone menu item with its own two tabs
                             (Letters / Settings), deliberately NOT tabs inside the Recruitment
                             hub, so it can be granted independently of the rest of the module.
                             It lands on the first tab the person actually holds. --}}
                        @if($can('general.recruitment.offers') || $can('general.recruitment.offers.settings'))
                            <a href="{{ $can('general.recruitment.offers') ? route('general.recruitment.offers.index') : route('general.recruitment.offers.settings.edit') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/recruitment/offers*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-file-signature text-xs"></i>
                                </span>
                                <span class="nav-text text-sm flex-1">Offering Letter</span>
                                @php $pendingOffers = $can('general.recruitment.offers') ? \App\Models\Recruitment\Offer::where('decision', 'pending')->count() : 0; @endphp
                                @if($pendingOffers > 0)
                                    <span class="nav-text bg-yellow-100 text-yellow-800 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        {{ $pendingOffers > 99 ? '99+' : $pendingOffers }}
                                    </span>
                                @endif
                            </a>
                        @endif

                        {{-- Letter Templates — the letters hub; opens the first of its five tabs the person can view. --}}
                        @if($can('general.letters.dashboard') || $can('general.letters.requests') || $can('general.letters.register')
                            || $can('general.letters.compose') || $can('general.letter-templates'))
                            <a href="{{ route('general.letters.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/letters*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-file-lines text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Letter Templates</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if($can('business'))
                <!-- BUSINESS DEV -->
                <div class="mb-2">
                    <a href="#"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('business') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-briefcase"></i>
                        </span>
                        <span class="nav-text font-medium">Business Dev</span>
                    </a>
                </div>
            @endif

            @if($can('tickets.inbox'))
                <!-- TICKET -->
                <div class="mb-2">
                    @php
                        $ticketActive = Request::is('ticket') || (Request::is('ticket/*') && !Request::is('ticket/task*') && !Request::is('ticket/consultant-workload*'));
                    @endphp
                    <a href="{{ route('ticket.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ $ticketActive ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-ticket-alt"></i>
                        </span>
                        <span class="nav-text font-medium">Ticket</span>
                    </a>
                </div>
            @endif

            @if($can('ticket.my-tasks'))
                <!-- MY TASKS -->
                <div class="mb-2">
                    <a href="{{ route('ticket.task') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket/task*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-tasks"></i>
                        </span>
                        <span class="nav-text font-medium">My Tasks</span>
                    </a>
                </div>
            @endif

            @if($can('ticket.consultant-workload'))
                <!-- CONSULTANT WORKLOAD -->
                <div class="mb-2">
                    <a href="{{ route('ticket.consultant-workload') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket/consultant-workload*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users-cog"></i>
                        </span>
                        <span class="nav-text font-medium">Consultant Workload</span>
                    </a>
                </div>
            @endif

            @if($can('tickets.staging'))
                <!-- TICKET VALIDATION -->
                <div class="mb-2">
                    <a href="{{ route('staging.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('staging-tickets*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-clipboard-check"></i>
                        </span>
                        <span class="nav-text font-medium flex-1">Ticket Validation</span>
                        @php
                            $unvalidatedCount = \App\Models\StagingTicket::where('status', 'unvalidated')->count();
                        @endphp
                        <span id="sidebarValidationBadge"
                            class="nav-text bg-yellow-400 text-gray-900 text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[20px] text-center {{ $unvalidatedCount > 0 ? '' : 'hidden' }}">
                            {{ $unvalidatedCount > 99 ? '99+' : $unvalidatedCount }}
                        </span>
                    </a>
                </div>
            @endif


            @if($can('delivery'))
                <!-- DELIVERY Dropdown -->
                <div class="mb-2">
                    <button onclick="toggleDeliveryDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') || Request::is('delivery/support*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-truck"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Delivery</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="deliveryChevron"></i>
                    </button>
                    <div id="deliveryDropdown"
                        class="nav-text {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') || Request::is('delivery/support*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('delivery.project'))
                            <a href="{{ route('projects.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-project-diagram text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Project</span>
                            </a>
                        @endif
                        @if($can('delivery.support'))
                            <a href="{{ route('delivery.support.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('delivery/support*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-headset text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Support</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if($can('control-center'))
                <!-- CONTROL CENTER -->
                @php $adminOpen = Request::is('admin*'); @endphp
                <div class="mb-2">
                    <button onclick="toggleAdminDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $adminOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Control Center</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $adminOpen ? 'rotate-180' : '' }}"
                            id="adminChevron"></i>
                    </button>
                    <div id="adminDropdown" class="nav-text {{ $adminOpen ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                        @if($can('control-center.overview'))
                            <a href="{{ route('admin.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-th-large text-xs"></i></span>
                                <span class="nav-text text-sm">Overview</span>
                            </a>
                        @endif
                        @if($can('control-center.activity-log'))
                            <a href="{{ route('admin.activity-log') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/activity-log*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-history text-xs"></i></span>
                                <span class="nav-text text-sm">Activity Log</span>
                            </a>
                        @endif
                        @if($can('control-center.login-log'))
                            <a href="{{ route('admin.login-log') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/login-log*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-sign-in-alt text-xs"></i></span>
                                <span class="nav-text text-sm">Login Log</span>
                            </a>
                        @endif
                        @if($can('control-center.audit-log'))
                        <a href="{{ route('admin.audit-log') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/audit-log*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-clipboard-list text-xs"></i></span>
                            <span class="nav-text text-sm">Audit Log</span>
                        </a>
                        @endif
                        @if($can('control-center.ai-settings'))
                        <a href="{{ route('admin.ai-settings') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/ai-settings*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-microchip text-xs"></i></span>
                            <span class="nav-text text-sm">AI Settings</span>
                        </a>
                        @endif
                        @if($can('control-center.sessions'))
                            <a href="{{ route('admin.sessions') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/sessions*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-users text-xs"></i></span>
                                <span class="nav-text text-sm">Active Sessions</span>
                            </a>
                        @endif
                        @if($can('control-center.failed-jobs'))
                            <a href="{{ route('admin.failed-jobs') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/failed-jobs*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-exclamation-triangle text-xs"></i></span>
                                <span class="nav-text text-sm">Failed Jobs</span>
                            </a>
                        @endif
                        @if($can('control-center.backup'))
                            <a href="{{ route('admin.backup') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/backup*') || Request::is('admin/export*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-database text-xs"></i></span>
                                <span class="nav-text text-sm">Backup & Export</span>
                            </a>
                        @endif
                        @if($can('control-center.sounds'))
                            <a href="{{ route('admin.sounds') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/sounds*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                        class="fas fa-music text-xs"></i></span>
                                <span class="nav-text text-sm">Notif Sounds</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @php
                $showSlaMenu = isset($showSlaMenu) ? $showSlaMenu : $can('sla');
                $canManageSla = isset($canManageSla) ? $canManageSla : ($can('sla.config') || $can('sla.manage'));
            @endphp
            @if($showSlaMenu || $canManageSla)
                <!-- SLA Dropdown -->
                @php $slaDropdownOpen = Request::is('sla*'); @endphp
                <div class="mb-2">
                    <button onclick="toggleSlaDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $slaDropdownOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-stopwatch"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">SLA</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $slaDropdownOpen ? 'rotate-180' : '' }}"
                            id="slaChevron"></i>
                    </button>
                    <div id="slaDropdown" class="nav-text {{ $slaDropdownOpen ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($showSlaMenu)
                            <a href="{{ route('sla.report') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('sla/report*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-chart-bar text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">SLA Report</span>
                            </a>
                        @endif
                        @if($canManageSla)
                            <a href="{{ route('sla.config') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('sla/config*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-cog text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">SLA Config</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @php
                $showRpmoMenu = isset($showRpmoMenu) ? $showRpmoMenu : ($can('rpmo') || $can('rpmo.overview'));
            @endphp
            @if($showRpmoMenu)
                <!-- RPMO -->
                @php $rpmoDropdownOpen = Request::is('rpmo*'); @endphp
                <div class="mb-2">
                    <button onclick="toggleRpmoDropdown()"
                        class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl {{ $rpmoDropdownOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all"
                        style="background:none;border:none;cursor:pointer;text-align:left;">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-cogs"></i>
                        </span>
                        <span class="nav-text font-medium flex-1">RPMO</span>
                        <span id="rpmoChevron"
                            class="nav-text transition-transform duration-200 {{ $rpmoDropdownOpen ? 'rotate-180' : '' }}">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </span>
                    </button>

                    <div id="rpmoSubmenu" class="{{ $rpmoDropdownOpen ? '' : 'hidden' }} pl-4 mt-1 space-y-1">
                        @if($can('rpmo.overview'))
                            <a href="{{ route('rpmo') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo') && !Request::is('rpmo/*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-tachometer-alt"></i>
                                </span>
                                <span class="nav-text">Overview</span>
                            </a>
                        @endif
                        @if($can('rpmo.periods'))
                            <a href="{{ route('rpmo.periods.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo/periods*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <span class="nav-text">Period Management</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if($can('legal'))
                <!-- LEGAL -->
                <div class="mb-2">
                    <a href="#"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('legal') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-balance-scale"></i>
                        </span>
                        <span class="nav-text font-medium">Legal</span>
                    </a>
                </div>
            @endif

            {{-- Dropdown Management.

                 🔴 D177 — `$can('management.cash-advance-settings')` DICABUT dari
                 kondisi ini. Sejak Keputusan D141 slug itu sengaja diperluas ke
                 sini karena Cash Advance Settings dulu HANYA bisa dibuka lewat
                 submenu Management → HR & General. Kini halaman itu juga jadi tab
                 "Settings" di hub "Cash Advance (CA)" (lihat baris `general.
                 cash-advance` di bawah, yang gerbangnya sudah diperluas dengan
                 slug yang sama) — orang Finance yang HANYA memegang slug setelan
                 ini tetap punya pintu masuk, tanpa perlu ikut melihat dropdown
                 Management yang tidak relevan baginya. --}}
            @if($can('management'))
                <!-- MANAJEMEN -->
                <div class="mb-2">
                    <button onclick="toggleManajemenDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('management*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Management</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="manajemenChevron"></i>
                    </button>
                    <div id="manajemenDropdown"
                        class="nav-text {{ Request::is('management*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        @if($can('management.roles'))
                            <a href="{{ route('management.roles.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/roles*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-user-tag text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Role</span>
                            </a>
                        @endif
                        @if($can('management.permissions'))
                            <a href="{{ route('management.permissions.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/permissions*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-key text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Menu Access</span>
                            </a>
                            <a href="{{ route('management.ess-settings.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/ess-settings*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-sliders-h text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">ESS Settings</span>
                            </a>
                        @endif
                        @if($can('management.holidays'))
                            <a href="{{ route('management.holidays.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/holidays*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-calendar-day text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Holidays</span>
                            </a>
                        @endif
                        @if($can('management.hidden-tickets'))
                            <a href="{{ route('management.hidden-tickets.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/hidden-tickets*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-eye-slash text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Hidden Tickets</span>
                            </a>
                        @endif
                        {{-- 🔴 D177 — Submenu "HR & General" DIHAPUS. Reimbursement/Purchase
                             Request/Cash Advance Settings (yang terakhir menghuninya setelah
                             D175 memindahkan Branches/Shifts/Attendance/Overtime Settings)
                             kini masing-masing jadi tab "Settings" di hub-nya sendiri —
                             Reimbursement Management, Purchase Request Management, dan
                             Cash Advance (CA) — persis pola D175. Submenu ini jadi kosong
                             begitu ketiganya pindah, jadi dihapus, bukan dibiarkan hampa. --}}
                        @if($can('management.employee'))
                            <div class="mt-1">
                                <button onclick="toggleMasterMgmtDropdown()"
                                    class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ Request::is('management/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-4 h-4 flex items-center justify-center">
                                        <i class="fas fa-users text-xs"></i>
                                    </span>
                                    <span class="nav-text text-sm flex-1">Employee</span>
                                    <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ Request::is('management/employee*') ? 'rotate-180' : '' }}"
                                        id="masterMgmtChevron"></i>
                                </button>
                                <div id="masterMgmtDropdown"
                                    class="nav-text {{ Request::is('management/employee*') ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                                    @if($can('management.employee.basic-data'))
                                        <a href="{{ route('management.employee.basic-data.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/basic-data*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-id-card text-xs"></i></span>
                                            <span class="nav-text text-xs">Basic Data</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.address'))
                                        <a href="{{ route('management.employee.address.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/address*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-map-marker-alt text-xs"></i></span>
                                            <span class="nav-text text-xs">Address</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.identification'))
                                        <a href="{{ route('management.employee.identification.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/identification*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-fingerprint text-xs"></i></span>
                                            <span class="nav-text text-xs">Identification</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.family'))
                                        <a href="{{ route('management.employee.family.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/family*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-users text-xs"></i></span>
                                            <span class="nav-text text-xs">Family</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.education'))
                                        <a href="{{ route('management.employee.education.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/education*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-graduation-cap text-xs"></i></span>
                                            <span class="nav-text text-xs">Education</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.qualification'))
                                        <a href="{{ route('management.employee.qualification.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/qualification*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-certificate text-xs"></i></span>
                                            <span class="nav-text text-xs">Qualification</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.contract'))
                                        <a href="{{ route('management.employee.contract.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/contract*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-file-contract text-xs"></i></span>
                                            <span class="nav-text text-xs">Contract</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.bank'))
                                        <a href="{{ route('management.employee.bank.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/bank*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-university text-xs"></i></span>
                                            <span class="nav-text text-xs">Bank Account</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.payment'))
                                        <a href="{{ route('management.employee.payment.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/payment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-money-bill text-xs"></i></span>
                                            <span class="nav-text text-xs">Basic Payment</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.attachment'))
                                        <a href="{{ route('management.employee.attachment.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/attachment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-paperclip text-xs"></i></span>
                                            <span class="nav-text text-xs">Attachment</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Divider -->
            <div class="my-6 border-t border-white border-opacity-10"></div>

            <!-- SETTINGS - Visible to all roles -->
            <div class="mb-2">
                <a href="{{ route('settings.index') }}"
                    class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('settings*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                    <span class="nav-icon w-5 h-5 flex items-center justify-center">
                        <i class="fas fa-cog"></i>
                    </span>
                    <span class="nav-text font-medium">Settings</span>
                </a>
            </div>
        </nav>
    @endif
    </div>{{-- /#sidebarScroll --}}
</aside>

<style>
    /* HC: pencarian menu & gulir sidebar. ID (#sidebar) menang spesifisitas atas aturan
       generik input dark mode di layout (`input { background:#374151 !important }`). */
    #sidebar #sidebarSearch {
        background-color: rgba(255, 255, 255, 0.12) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.18);
    }
    #sidebar #sidebarSearch::placeholder { color: rgba(255, 255, 255, 0.6) !important; }
    #sidebar #sidebarSearch::-webkit-search-cancel-button { display: none; }
    #sidebar .sb-hide { display: none !important; }
    /* Favorit */
    #sidebar .sb-label-inline {
        font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: rgba(255, 255, 255, 0.55);
    }
    #sidebarFavList { max-height: 30vh; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.35) transparent; }
    #sidebar .sb-star {
        margin-left: auto; padding: 2px 4px; border-radius: 6px; font-size: 12px; line-height: 1;
        color: rgba(255, 255, 255, 0.75); opacity: 0; cursor: pointer; transition: opacity .15s;
    }
    #sidebar a.nav-link:hover .sb-star, #sidebar .sb-star:focus, #sidebar .sb-star.on { opacity: 1; }
    #sidebar .sb-star.on { color: #fde047; }
    #sidebar .sb-star:hover { background: rgba(255, 255, 255, 0.18); }
    @media (hover: none) { #sidebar .sb-star { opacity: .55; } #sidebar .sb-star.on { opacity: 1; } }
    #sidebarScroll { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.35) transparent; }
    #sidebarScroll::-webkit-scrollbar { width: 6px; }
    #sidebarScroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.35); border-radius: 9999px; }
    /* Label seksi (mis. HUMAN CAPITAL) */
    #sidebar .sb-label {
        font-size: 10px; letter-spacing: .12em; text-transform: uppercase;
        color: rgba(255, 255, 255, 0.55); padding: 14px 16px 4px;
    }
</style>

<script>
    /**
     * Pencarian menu sidebar. Menyaring tautan (`a.nav-link`) di dalam #sidebarScroll:
     *  - yang cocok tetap tampil, induk dropdown-nya DIBUKA otomatis;
     *  - blok tingkat atas tanpa tautan cocok disembunyikan (label seksi ikut);
     *  - bila NAMA grup yang cocok (mis. "hr"), seluruh isi grup ditampilkan;
     *  - dikosongkan / Esc → keadaan dropdown dikembalikan persis seperti sebelum mencari.
     * Enter membuka tautan cocok pertama.
     */
    (function () {
        var input = document.getElementById('sidebarSearch');
        var scroll = document.getElementById('sidebarScroll');
        var clearBtn = document.getElementById('sidebarSearchClear');
        if (!input || !scroll) { return; }

        var wasHidden = null; // elemen `.hidden` sebelum pencarian pertama

        function topBlocks() {
            var nav = scroll.querySelector('nav');
            return Array.prototype.slice.call((nav || scroll).children);
        }

        function restore() {
            scroll.querySelectorAll('.sb-hide').forEach(function (el) { el.classList.remove('sb-hide'); });
            if (wasHidden) {
                wasHidden.forEach(function (el) { el.classList.add('hidden'); });
                wasHidden = null;
            }
        }

        function apply() {
            var q = input.value.trim().toLowerCase();
            clearBtn.classList.toggle('hidden', q === '');
            if (q === '') { restore(); return; }

            if (!wasHidden) {
                wasHidden = [];
                scroll.querySelectorAll('.hidden').forEach(function (el) { wasHidden.push(el); });
            }
            scroll.querySelectorAll('.sb-hide').forEach(function (el) { el.classList.remove('sb-hide'); });

            function has(el) { return (el.textContent || '').toLowerCase().indexOf(q) !== -1; }

            // Sebuah tautan cocok bila teksnya cocok, ATAU nama grup dropdown yang
            // MEMBUNGKUSNYA cocok (tombol toggle = saudara sebelum wadah dropdown).
            // Nama sub-grup hanya mencocokkan isinya sendiri, bukan grup induknya.
            function linkMatches(a) {
                if (has(a)) { return true; }
                for (var p = a.parentElement; p && p !== scroll; p = p.parentElement) {
                    var prev = p.previousElementSibling;
                    if (prev && prev.matches && prev.matches('button.nav-link') && has(prev)) { return true; }
                }
                return false;
            }

            var links = Array.prototype.slice.call(scroll.querySelectorAll('a.nav-link'));
            var matched = links.filter(linkMatches);

            links.forEach(function (a) { if (matched.indexOf(a) === -1) { a.classList.add('sb-hide'); } });
            matched.forEach(function (a) {
                for (var p = a.parentElement; p && p !== scroll; p = p.parentElement) {
                    p.classList.remove('hidden');
                }
            });

            // Tombol toggle grup yang isinya tak ada yang cocok ikut disembunyikan.
            scroll.querySelectorAll('button.nav-link').forEach(function (b) {
                var box = b.nextElementSibling;
                if (box && !box.querySelector('a.nav-link:not(.sb-hide)')) { b.classList.add('sb-hide'); }
            });

            // Blok tingkat atas (dan label seksi) tanpa satu pun tautan tampil disembunyikan.
            topBlocks().forEach(function (block) {
                var shown = block.matches('a.nav-link')
                    ? !block.classList.contains('sb-hide')
                    : !!block.querySelector('a.nav-link:not(.sb-hide)');
                if (!shown) { block.classList.add('sb-hide'); }
            });
        }

        input.addEventListener('input', apply);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { input.value = ''; apply(); }
            if (e.key === 'Enter') {
                var first = Array.prototype.slice.call(scroll.querySelectorAll('a.nav-link'))
                    .filter(function (a) { return !a.closest('.sb-hide') && !a.classList.contains('sb-hide') && a.getAttribute('href') && a.getAttribute('href') !== '#'; })[0];
                if (first) { window.location.href = first.getAttribute('href'); }
            }
        });
        clearBtn.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
    })();

    /**
     * FAVORIT (HC-D25). Bintang muncul saat menu di-hover/fokus; klik menyematkan menu ke
     * bagian "Favorites" di header tetap (maks. window.__sidebarFavoritesMax).
     *  - Yang disimpan hanya JALUR URL; tampilannya SALINAN tautan yang sudah ada di sidebar
     *    (sudah disaring izin di server). Jalur yang tautannya tak ada lagi tidak ditampilkan.
     *  - Simpan ke server semantik "replace"; bila gagal, keadaan dikembalikan + pemberitahuan.
     *  - Tidak memakai innerHTML dengan data tersimpan (hanya cloneNode) → tidak ada jalur XSS.
     */
    (function () {
        var scroll = document.getElementById('sidebarScroll');
        var box = document.getElementById('sidebarFavorites');
        var list = document.getElementById('sidebarFavList');
        var toggle = document.getElementById('sidebarFavToggle');
        var chevron = document.getElementById('sidebarFavChevron');
        if (!scroll || !box || !list || !window.__sidebarFavoritesEnabled) { return; }

        var MAX = window.__sidebarFavoritesMax || 6;
        var COLLAPSED_KEY = 'ecosystem:sidebar:favorites:collapsed';
        var favs = Array.isArray(window.__sidebarFavorites) ? window.__sidebarFavorites.slice() : [];
        var FORBIDDEN = /^\/(api|auth|sidebar)(\/|$)|^\/logout(\/|$)/;

        function pathOf(a) {
            var href = a.getAttribute('href');
            if (!href || href === '#' || href.indexOf('javascript:') === 0) { return null; }
            try {
                var u = new URL(href, window.location.origin);
                if (u.origin !== window.location.origin || FORBIDDEN.test(u.pathname)) { return null; }
                return u.pathname.length > 1 ? u.pathname.replace(/\/+$/, '') : u.pathname;
            } catch (e) { return null; }
        }

        var links = Array.prototype.slice.call(scroll.querySelectorAll('a.nav-link')).filter(function (a) { return pathOf(a) !== null; });

        function makeStar(on) {
            var s = document.createElement('span');
            s.className = 'sb-star' + (on ? ' on' : '');
            s.setAttribute('role', 'button');
            s.setAttribute('tabindex', '0');
            var label = on ? 'Remove from favorites' : 'Add to favorites';
            s.setAttribute('aria-label', label);
            s.title = label;
            var i = document.createElement('i');
            i.className = (on ? 'fas' : 'far') + ' fa-star';
            s.appendChild(i);
            return s;
        }

        function existsInSidebar(path) {
            return links.some(function (a) { return pathOf(a) === path; });
        }

        function render() {
            // Bintang pada tautan utama.
            links.forEach(function (a) {
                var old = a.querySelector('.sb-star');
                if (old) { old.remove(); }
                a.appendChild(makeStar(favs.indexOf(pathOf(a)) !== -1));
            });
            // Bagian Favorites: salinan tautan yang MASIH ada (berizin).
            list.textContent = '';
            var shown = 0;
            favs.forEach(function (path) {
                var src = links.filter(function (a) { return pathOf(a) === path; })[0];
                if (!src) { return; }
                var clone = src.cloneNode(true);
                var oldStar = clone.querySelector('.sb-star');
                if (oldStar) { oldStar.remove(); }
                clone.removeAttribute('id');
                clone.appendChild(makeStar(true));
                list.appendChild(clone);
                shown++;
            });
            box.classList.toggle('hidden', shown === 0);
        }

        function save(previous) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            fetch('/sidebar/favorites', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': meta ? meta.content : '' },
                body: JSON.stringify({ paths: favs })
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
              .then(function (res) {
                  if (!res.ok || !res.body.success) { throw new Error('save failed'); }
                  favs = res.body.data.paths.slice();
                  render();
              })
              .catch(function () {
                  favs = previous;
                  render();
                  if (window.showNotification) { window.showNotification('Could not save your favorites. Please try again.', 'error'); }
              });
        }

        function toggleFavorite(path) {
            var previous = favs.slice();
            var idx = favs.indexOf(path);
            if (idx !== -1) {
                favs.splice(idx, 1);
            } else {
                // Buang dulu favorit yang tautannya sudah tak ada (izin dicabut) agar tidak memakan kuota diam-diam.
                favs = favs.filter(existsInSidebar);
                if (favs.length >= MAX) {
                    favs = previous;
                    if (window.showNotification) { window.showNotification('You can pin up to ' + MAX + ' favorites. Remove one first.', 'warning'); }
                    return;
                }
                favs.push(path);
            }
            render();
            save(previous);
        }

        function starClick(e) {
            var star = e.target.closest ? e.target.closest('.sb-star') : null;
            if (!star) { return; }
            var a = star.closest('a.nav-link');
            var path = a ? pathOf(a) : null;
            e.preventDefault();
            e.stopPropagation();
            if (path) { toggleFavorite(path); }
        }
        var sidebar = document.getElementById('sidebar');
        sidebar.addEventListener('click', starClick, true);
        sidebar.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('sb-star')) { starClick(e); }
        }, true);

        // Bagian Favorites dapat dilipat; pilihan diingat di peramban ini.
        function applyCollapsed(collapsed) {
            list.classList.toggle('hidden', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            if (chevron) { chevron.classList.toggle('rotate-180', collapsed); }
        }
        try { applyCollapsed(localStorage.getItem(COLLAPSED_KEY) === '1'); } catch (e) { /* opsional */ }
        toggle.addEventListener('click', function () {
            var collapsed = !list.classList.contains('hidden');
            applyCollapsed(collapsed);
            try { localStorage.setItem(COLLAPSED_KEY, collapsed ? '1' : '0'); } catch (e) { /* opsional */ }
        });

        render();
    })();

    /**
     * Sidebar TIDAK kembali ke atas setelah pindah halaman. Halaman dimuat ulang penuh
     * (bukan SPA), sehingga posisi gulir #sidebarScroll dan dropdown yang dibuka manual
     * hilang setiap klik menu. Keadaan disimpan di sessionStorage (per tab; bersih saat
     * tab ditutup) dan dipulihkan SINKRON sebelum browser melukis halaman — tanpa kedipan.
     *  1. dropdown yang dibuka manual dibuka kembali;
     *  2. posisi gulir dipulihkan;
     *  3. item AKTIF dipastikan terlihat (mis. kunjungan pertama, atau membuka URL langsung).
     * Posisi TIDAK disimpan selama kolom pencarian terisi (daftar sedang tersaring, jadi
     * posisinya tidak berlaku pada daftar penuh di halaman berikutnya).
     */
    (function () {
        var KEY = 'ecosystem:sidebar:v1';
        var scroll = document.getElementById('sidebarScroll');
        var search = document.getElementById('sidebarSearch');
        if (!scroll) { return; }

        function load() { try { return JSON.parse(sessionStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
        function save(patch) {
            try {
                var s = load();
                for (var k in patch) { s[k] = patch[k]; }
                sessionStorage.setItem(KEY, JSON.stringify(s));
            } catch (e) { /* storage dinonaktifkan/penuh: fitur ini opsional */ }
        }

        var state = load();

        // 1. Buka kembali dropdown yang dibuka manual (server hanya membuka grup halaman aktif).
        var open = state.open || {};
        Object.keys(open).forEach(function (dropdownId) {
            var box = document.getElementById(dropdownId);
            if (!box) { return; }
            box.classList.remove('hidden');
            var chevron = document.getElementById(open[dropdownId]);
            if (chevron && !chevron.classList.contains('rotate-180')) { chevron.classList.add('rotate-180'); }
        });

        // 2. Pulihkan posisi gulir.
        if (typeof state.scroll === 'number') { scroll.scrollTop = state.scroll; }

        // 3. Pastikan item aktif terlihat.
        var active = scroll.querySelector('a.nav-link.bg-opacity-15, a.nav-link.active');
        if (active) {
            var r = active.getBoundingClientRect();
            var s = scroll.getBoundingClientRect();
            if (r.height > 0 && (r.top < s.top || r.bottom > s.bottom)) {
                scroll.scrollTop += (r.top - s.top) - (s.height / 2 - r.height / 2);
            }
        }

        function searching() { return !!(search && search.value.trim() !== ''); }
        var timer = null;
        scroll.addEventListener('scroll', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { if (!searching()) { save({ scroll: scroll.scrollTop }); } }, 80);
        }, { passive: true });
        window.addEventListener('pagehide', function () { if (!searching()) { save({ scroll: scroll.scrollTop }); } });

        // Chevron milik sebuah dropdown = elemen ber-id `*Chevron` di tombol toggle-nya
        // (saudara sebelum wadah dropdown).
        function chevronOf(box) {
            var btn = box.previousElementSibling;
            var ch = btn && btn.querySelector ? btn.querySelector('[id$="Chevron"]') : null;
            return ch ? ch.id : null;
        }
        var boxes = scroll.querySelectorAll('[id$="Dropdown"], [id$="Submenu"]');

        // Catat setiap buka/tutup dropdown dengan MENGAMATI kelas `hidden` di DOM. Layout
        // (dashboard.blade.php) punya fungsi toggle sendiri per grup dengan variabel status
        // berbeda-beda, jadi mengait ke satu fungsi tidak akan menjangkau semuanya.
        // Selama pencarian aktif perubahan diabaikan (pencarian membuka grup sementara).
        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                if (searching()) { return; }
                mutations.forEach(function (m) {
                    var box = m.target;
                    var chevron = chevronOf(box);
                    if (!chevron) { return; }
                    var o = load().open || {};
                    if (box.classList.contains('hidden')) { delete o[box.id]; } else { o[box.id] = chevron; }
                    save({ open: o });
                });
            });
            boxes.forEach(function (box) { observer.observe(box, { attributes: true, attributeFilter: ['class'] }); });
        }

        // Layout memegang variabel status sendiri (mis. `isHrGeneralDropdownOpen`) yang
        // diisi dari URL SETELAH skrip ini berjalan. Bila dropdown sudah dibuka oleh
        // pemulihan di atas tetapi variabelnya masih `false`, klik pertama pengguna
        // menjadi "buka lagi" — terasa tidak bereaksi. Sinkronkan variabelnya dengan DOM.
        document.addEventListener('DOMContentLoaded', function () {
            boxes.forEach(function (box) {
                var chevron = chevronOf(box);
                if (!chevron) { return; }
                var base = chevron.replace(/Chevron$/, '');
                var name = 'is' + base.charAt(0).toUpperCase() + base.slice(1) + 'DropdownOpen';
                if (!/^is[A-Za-z]+DropdownOpen$/.test(name)) { return; }
                try {
                    // Eval tidak langsung = lingkup global, menjangkau `var` maupun `let` di layout.
                    (0, eval)('if (typeof ' + name + ' !== "undefined") { ' + name + ' = ' + (!box.classList.contains('hidden')) + '; }');
                } catch (e) { /* CSP melarang eval: hanya klik pertama yang tidak bereaksi */ }
            });
        });
    })();
</script>

<script>
    function toggleSidebarDropdown(dropdownId, chevronId) {
        const dropdown = document.getElementById(dropdownId);
        const chevron = document.getElementById(chevronId);
        if (dropdown) dropdown.classList.toggle('hidden');
        if (chevron) chevron.classList.toggle('rotate-180');
    }

    function toggleCalendarDropdown() { toggleSidebarDropdown('calendarDropdown', 'calendarChevron'); }
    function toggleReportingDropdown() { toggleSidebarDropdown('reportingDropdown', 'reportingChevron'); }
    function toggleMasterDropdown() { toggleSidebarDropdown('masterDropdown', 'masterChevron'); }
    function toggleHrGeneralDropdown() { toggleSidebarDropdown('hrGeneralDropdown', 'hrGeneralChevron'); }
    function toggleDeliveryDropdown() { toggleSidebarDropdown('deliveryDropdown', 'deliveryChevron'); }
    function toggleAdminDropdown() { toggleSidebarDropdown('adminDropdown', 'adminChevron'); }
    function toggleSlaDropdown() { toggleSidebarDropdown('slaDropdown', 'slaChevron'); }
    function toggleRpmoDropdown() { toggleSidebarDropdown('rpmoSubmenu', 'rpmoChevron'); }
    function toggleManajemenDropdown() { toggleSidebarDropdown('manajemenDropdown', 'manajemenChevron'); }
    // D182 — SATU fungsi generik untuk SELURUH grup ESS yang admin buat lewat
    // Management -> ESS Settings, bukan satu fungsi bernama per grup seperti
    // dropdown lain di atas — jumlah dan nama grupnya ditentukan admin saat
    // dipakai, jadi tidak bisa dituliskan satu per satu di sini lebih dulu.
    function toggleEssGroupDropdown(groupId) { toggleSidebarDropdown('essGroup' + groupId + 'Dropdown', 'essGroup' + groupId + 'Chevron'); }
    function toggleMgmtDropdown() { toggleSidebarDropdown('manajemenDropdown', 'manajemenChevron'); }
    function toggleHrGeneralMgmtDropdown() { toggleSidebarDropdown('hrGeneralMgmtDropdown', 'hrGeneralMgmtChevron'); }
    function toggleMasterMgmtDropdown() { toggleSidebarDropdown('masterMgmtDropdown', 'masterMgmtChevron'); }
</script>

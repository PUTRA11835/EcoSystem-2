<aside id="sidebar"
    class="sidebar-transition fixed inset-y-0 left-0 h-screen overflow-y-auto {{ $preferences['sidebar_style'] === 'gradient' ? 'primary-gradient' : 'primary-solid' }} text-white shadow-2xl z-50 w-64 -translate-x-full lg:translate-x-0">
    <!-- Logo Section -->
    <div class="sidebar-logo p-5 pb-2 flex items-center justify-center">
        <div class="w-full rounded-xl p-3 backdrop-blur-sm">
            <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem Logo" class="w-full h-auto" />
        </div>
    </div>

    <!-- Navigation Menu -->
    @hasSection('sidebar-nav')
        @yield('sidebar-nav')
    @else
        <nav class="py-4 px-3 space-y-1">
            @php
                $essConfig = \App\Http\Controllers\Management\EssSettingsController::getEssSettings();
            @endphp

            @if(!empty($essConfig['home']))
                <div class="mb-2">
                    <a href="{{ route('dashboard') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('dashboard') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-home"></i>
                        </span>
                        <span class="nav-text font-medium">Home</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['my_profile']))
                <div class="mb-2">
                    <a href="{{ route('profile.my') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('my-profile*') || Request::is('profile*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-user-circle"></i>
                        </span>
                        <span class="nav-text font-medium">My Profile</span>
                    </a>
                </div>
            @endif



            @if(!empty($essConfig['my_attendance']))
                <div class="mb-2">
                    <a href="{{ route('general.my-attendance.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/my-attendance*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-user-clock"></i>
                        </span>
                        <span class="nav-text font-medium">My Attendance</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['my_leave_permit']))
                <div class="mb-2">
                    <a href="{{ route('my-leave-permit') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('my-leave-permit*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-calendar-check"></i>
                        </span>
                        <span class="nav-text font-medium">My Leave & Permit</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['overtime']))
                <div class="mb-2">
                    <a href="{{ route('general.my-overtime.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/my-overtime*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-business-time"></i>
                        </span>
                        <span class="nav-text font-medium">Overtime</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['expense_reimbursement']))
                <div class="mb-2">
                    <a href="{{ route('general.my-reimbursement.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/my-reimbursement*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-receipt"></i>
                        </span>
                        <span class="nav-text font-medium">My Reimbursement</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['paystub']))
                <div class="mb-2">
                    <a href="{{ route('coming-soon', ['feature' => 'Paystub']) }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </span>
                        <span class="nav-text font-medium">Paystub</span>
                    </a>
                </div>
            @endif

            {{-- Sejak 2 Sep 2026 item ini menunjuk halaman sungguhan, bukan lagi
                 route('coming-soon'). Pola yang sama dipakai My Attendance,
                 Overtime, dan Reimbursement saat modulnya jadi.

                 DUA GERBANG, keduanya harus terbuka: sakelar ESS di bawah
                 mengatur apakah itemnya DIRENDER, sementara slug
                 `general.my-purchase-request` di Control Center mengatur apakah
                 RUTENYA boleh dibuka. Item yang terlihat tetapi menolak saat
                 diklik berarti slugnya belum dibagikan. --}}
            @if(!empty($essConfig['purchase_request']))
                <div class="mb-2">
                    <a href="{{ route('general.my-purchase-request.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/my-purchase-request*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-shopping-cart"></i>
                        </span>
                        <span class="nav-text font-medium">Purchase Request</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['advance_payment_ca']))
                <div class="mb-2">
                    <a href="{{ route('coming-soon', ['feature' => 'Advance Payment (CA)']) }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-hand-holding-usd"></i>
                        </span>
                        <span class="nav-text font-medium">Advance Payment (CA)</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['advance_payment_car']))
                <div class="mb-2">
                    <a href="{{ route('coming-soon', ['feature' => 'Advance Payment Report (CAR)']) }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-file-contract"></i>
                        </span>
                        <span class="nav-text font-medium">Advance Payment Report (CAR)</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['loans']))
                <div class="mb-2">
                    <a href="{{ route('coming-soon', ['feature' => 'Loans']) }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-landmark"></i>
                        </span>
                        <span class="nav-text font-medium">My Loans</span>
                    </a>
                </div>
            @endif

            @if(!empty($essConfig['my_kpis']))
                <div class="mb-2">
                    <a href="{{ route('general.my-kpi.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/my-kpi*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <span class="nav-text font-medium">My KPI</span>
                    </a>
                </div>
            @endif


            @if(!empty($essConfig['ai_assistant']) && $can('ai-assistant'))
            <!-- AI ASSISTANT -->
            <div class="mb-2">
                <a href="{{ route('ai-assistant') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ai-assistant*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                    <span class="nav-icon w-5 h-5 flex items-center justify-center">
                        <i class="fas fa-robot"></i>
                    </span>
                    <span class="nav-text font-medium">AI Assistant</span>
                </a>
            </div>
            @endif

            @if(!empty($essConfig['ai_research']) && $can('ai-research'))
            <!-- AI RESEARCH -->
            <div class="mb-2">
                <a href="{{ route('ai-research') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ai-research*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                    <span class="nav-icon w-5 h-5 flex items-center justify-center">
                        <i class="fas fa-magnifying-glass-chart"></i>
                    </span>
                    <span class="nav-text font-medium">AI Research</span>
                </a>
            </div>
            @endif

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

            @if($can('general') || $can('hr_general.leave_permit.admin') || $can('general.attendance') || $can('general.attendance.correction') || $can('general.overtime') || $can('general.reimbursement') || $can('general.purchase-request'))
                <!-- HR & GENERAL -->
                @php
                    $hrGeneralOpen = Request::is('hr-general*') || Request::is('general/attendance*') || Request::is('general/overtime*') || Request::is('general/reimbursement*') || Request::is('general/purchase-request*');
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

                        @if($can('general.attendance') || $can('general'))
                            <a href="{{ route('general.attendance.daily') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/attendance*') && !Request::is('general/attendance/corrections*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-clipboard-list text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Attendance Recap</span>
                            </a>
                        @endif

                        @if($can('general.attendance.correction') || $can('general'))
                            <a href="{{ route('general.attendance.corrections.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/attendance/corrections*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-user-check text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Attendance Corrections</span>
                            </a>
                        @endif

                        @if($can('general.overtime') || $can('general'))
                            <a href="{{ route('general.overtime.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/overtime*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-clock text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Overtime Review</span>
                            </a>
                        @endif

                        @if($can('general.reimbursement') || $can('general'))
                            <a href="{{ route('general.reimbursement.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/reimbursement*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-receipt text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Reimbursement</span>
                            </a>
                        @endif

                        @if($can('general.purchase-request') || $can('general'))
                            <a href="{{ route('general.purchase-request.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('general/purchase-request*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-cart-shopping text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Purchase Request Review</span>
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
                        @php
                            $hrGeneralSettingsActive = Request::is('general/settings*');
                        @endphp
                        
                        @if($can('general.settings.branches') || $can('general.settings.shifts') || $can('general.settings.attendance') || $can('general.settings.overtime') || $can('general.settings.reimbursement') || $can('general.settings.purchase-request'))
                        <div class="mt-1">
                            <button onclick="toggleHrGeneralMgmtDropdown()" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ $hrGeneralSettingsActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-users-cog text-xs"></i>
                                </span>
                                <span class="nav-text text-sm flex-1">HR &amp; General</span>
                                <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $hrGeneralSettingsActive ? 'rotate-180' : '' }}" id="hrGeneralMgmtChevron"></i>
                            </button>
                            <div id="hrGeneralMgmtDropdown" class="nav-text {{ $hrGeneralSettingsActive ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                                @if($can('general.settings.branches'))
                                <a href="{{ route('general.settings.branches.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/branches*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-map-marker-alt text-xs"></i></span>
                                    <span class="nav-text text-xs">Branches</span>
                                </a>
                                @endif
                                @if($can('general.settings.shifts'))
                                <a href="{{ route('general.settings.shifts.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/shifts*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-clock text-xs"></i></span>
                                    <span class="nav-text text-xs">Shifts</span>
                                </a>
                                @endif
                                @if($can('general.settings.attendance'))
                                <a href="{{ route('general.settings.attendance.edit') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/attendance*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-sliders text-xs"></i></span>
                                    <span class="nav-text text-xs">Attendance Settings</span>
                                </a>
                                @endif
                                @if($can('general.settings.overtime'))
                                <a href="{{ route('general.settings.overtime.edit') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/overtime*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-business-time text-xs"></i></span>
                                    <span class="nav-text text-xs">Overtime Settings</span>
                                </a>
                                @endif
                                @if($can('general.settings.reimbursement'))
                                <a href="{{ route('general.settings.reimbursement.edit') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/reimbursement*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-receipt text-xs"></i></span>
                                    <span class="nav-text text-xs">Reimbursement Settings</span>
                                </a>
                                @endif
                                @if($can('general.settings.purchase-request'))
                                <a href="{{ route('general.settings.purchase-request.edit') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/purchase-request*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="w-3 h-3 flex items-center justify-center"><i class="fas fa-cart-shopping text-xs"></i></span>
                                    <span class="nav-text text-xs">Purchase Request Settings</span>
                                </a>
                                @endif
                                    @if($can('general.settings.kpi'))
                                        <a href="{{ route('general.settings.kpi.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('general/settings/kpi*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="w-3 h-3 flex items-center justify-center"><i
                                                    class="fas fa-layer-group text-xs"></i></span>
                                            <span class="nav-text text-xs">KPI Templates</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
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
</aside>

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
    function toggleMgmtDropdown() { toggleSidebarDropdown('manajemenDropdown', 'manajemenChevron'); }
    function toggleHrGeneralMgmtDropdown() { toggleSidebarDropdown('hrGeneralMgmtDropdown', 'hrGeneralMgmtChevron'); }
    function toggleMasterMgmtDropdown() { toggleSidebarDropdown('masterMgmtDropdown', 'masterMgmtChevron'); }
</script>

<aside id="sidebar" data-rail="{{ $__env->hasSection('sidebar-nav') ? 'off' : 'on' }}"
    class="sidebar-transition fixed inset-y-0 left-0 h-screen flex flex-col overflow-hidden {{ $preferences['sidebar_style'] === 'gradient' ? 'primary-gradient' : 'primary-solid' }} text-white shadow-2xl z-50 w-64 -translate-x-full lg:translate-x-0">
    @php
        $sbFavoritesEnabled = (session('user.type') ?? null) === 'employee';
        $sbFavorites = $sbFavoritesEnabled
            ? app(\App\Services\Sidebar\SidebarFavoriteService::class)->forEmployee((int) session('user.id'))
            : [];
    @endphp

    @include('partials.sidebar-icons')
    <script>
        window.__sidebarFavoritesEnabled = @json($sbFavoritesEnabled);
        window.__sidebarFavorites = @json($sbFavorites);
        window.__sidebarFavoritesMax = {{ \App\Services\Sidebar\SidebarFavoriteService::MAX }};
        (function () {
            try {
                var a = document.getElementById('sidebar');
                if (a && a.getAttribute('data-rail') !== 'off' && localStorage.getItem('ecosystem:sidebar:layout:v1') === 'rail') {
                    document.documentElement.setAttribute('data-sb-layout', 'rail');
                }
            } catch (e) { /* penyimpanan dinonaktifkan: tampilan penuh */ }
        })();
    </script>

    <div id="sidebarHeader" class="shrink-0 px-4 pt-4 pb-3">

        <div class="sidebar-logo flex items-center justify-center">
            <div class="w-full rounded-xl px-3 backdrop-blur-sm">
                <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem Logo" class="w-full h-auto mx-auto" style="max-height:64px; object-fit:contain;" />
            </div>
        </div>

        <div class="mt-3 flex items-center gap-1.5 sb-search-row">
        <div class="relative flex-1 min-w-0">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white text-opacity-60 pointer-events-none"></i>
            <input id="sidebarSearch" type="search" autocomplete="off" spellcheck="false"
                placeholder="Search menu..."
                aria-label="Search menu"
                class="w-full pl-9 pr-8 py-2 rounded-lg text-sm text-white placeholder-white placeholder-opacity-60 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-40">
            <button type="button" id="sidebarSearchClear" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-white text-opacity-70 hover:text-opacity-100 text-xs" aria-label="Clear search">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <button type="button" id="sidebarToggleAll" onclick="toggleAllSidebarSections()" class="sb-toggle-all"
            aria-label="Collapse all sections" title="Collapse all sections">
            <i class="fas fa-angles-up"></i>
        </button>
        </div>

        <button type="button" id="sidebarRailSearch" class="sb-rail-only" onclick="sbRailSearch()" title="Search menu" aria-label="Search menu">
            <svg class="sb-ico" aria-hidden="true" focusable="false"><use href="#ti-search"/></svg>
        </button>

    </div>

    <div id="sidebarScroll" class="flex-1 min-h-0 overflow-y-auto">
    @hasSection('sidebar-nav')
        @yield('sidebar-nav')
    @else
        <nav class="py-4 px-3 space-y-1">
            @php
                $essConfig    = \App\Http\Controllers\Management\EssSettingsController::getEssSettings();
                // Konsultan eksternal tidak melihat menu karyawan yang tak relevan (config/external_ess.php); Internal tidak berubah.
                if (($sessionEmployee = \App\Models\Employee::find(session('user.id'))) && $sessionEmployee->isExternalConsultant()) {
                    foreach (config('external_ess.hidden_nav', []) as $essHideKey) { $essConfig[$essHideKey] = false; }
                }
                $essGroupData = \App\Http\Controllers\Management\EssSettingsController::getEssGroups();

                $essNav = [
                    'home' => [
                        'label'   => 'Dashboard',
                        'icon'    => 'fas fa-gauge-high',
                        'href'    => route('dashboard'),
                        'active'  => Request::is('dashboard'),
                        'visible' => !empty($essConfig['home']),
                    ],
                    'my_profile' => [
                        'label'   => 'My Profile',
                        'icon'    => 'fas fa-user-circle',
                        'href'    => route('profile.my'),
                        'active'  => Request::is('my-profile*'),
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
                    'paystub' => [
                        'label'   => 'Paystub',
                        'icon'    => 'fas fa-file-invoice-dollar',
                        'href'    => route('general.my-paystub.index'),
                        'active'  => Request::is('general/my-paystub*'),
                        'visible' => !empty($essConfig['paystub']),
                    ],
                    'purchase_request' => [
                        'label'   => 'My Purchase Request',
                        'icon'    => 'fas fa-shopping-cart',
                        'href'    => route('general.my-purchase-request.index'),
                        'active'  => Request::is('general/my-purchase-request*'),
                        'visible' => !empty($essConfig['purchase_request']),
                    ],
                    'advance_payment_ca' => [
                        'label'   => 'My Cash Advance',
                        'icon'    => 'fas fa-hand-holding-usd',
                        'href'    => route('general.my-cash-advance.index'),
                        'active'  => Request::is('general/my-cash-advance*'),
                        // CA and CAR are two tabs of one page; the entry shows if either switch is on.
                        'visible' => !empty($essConfig['advance_payment_ca']) || !empty($essConfig['advance_payment_car']),
                    ],
                    'loans' => [
                        'label'   => 'My Loans',
                        'icon'    => 'fas fa-landmark',
                        'href'    => route('coming-soon', ['feature' => 'Loans']),
                        'active'  => false,
                        'visible' => !empty($essConfig['loans']),
                    ],
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

                $essSecOf = ['home' => 'top', 'ai_assistant' => 'tools', 'ai_research' => 'tools'];
                $essRenderedGroupIds = [];
                $essOutput = [];

                foreach ($essNav as $essKey => $essItem) {
                    $essGroupId = $essGroupData['assignments'][$essKey] ?? null;

                    if ($essGroupId === null) {
                        $essOutput[] = ['type' => 'item', 'key' => $essKey, 'item' => $essItem];
                        continue;
                    }

                    if (in_array($essGroupId, $essRenderedGroupIds, true)) {
                        continue; // sudah dirender lewat kemunculan pertama grup ini
                    }

                    $essGroup = collect($essGroupData['groups'])->firstWhere('id', $essGroupId);

                    if (!$essGroup) {
                        $essOutput[] = ['type' => 'item', 'key' => $essKey, 'item' => $essItem];
                        continue;
                    }

                    $essMembers = collect($essNav)
                        ->filter(fn ($it, $k) => ($essGroupData['assignments'][$k] ?? null) === $essGroupId)
                        ->values();

                    $essOutput[] = ['type' => 'group', 'key' => $essKey, 'group' => $essGroup, 'members' => $essMembers];
                    $essRenderedGroupIds[] = $essGroupId;
                }
            @endphp

            @php $sb = []; @endphp

@php ob_start(); @endphp
@include('partials.ess-nav-list', ['essSecs' => ['top']])
@php $sb['dashboard'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
@include('partials.ess-nav-list', ['essSecs' => ['workspace']])
@php $sb['ess_workspace'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
@include('partials.ess-nav-list', ['essSecs' => ['tools']])
@php $sb['ess_tools'] = ob_get_clean(); @endphp

            @php
                $showEvents = !empty($essConfig['events_calendar']) && ($can('calendar.events') || Auth::check());
                $showTimesheets = !empty($essConfig['my_timesheet']) && ($can('calendar.timesheets') || Auth::check());
                $calendarGate = ($can('calendar') || Auth::check());
            @endphp
@php ob_start(); @endphp
@if($calendarGate && $showEvents)
                @include('partials.ess-nav-item', [
                    'href'   => route('calendar.events'),
                    'icon'   => 'fas fa-calendar-alt',
                    'label'  => 'Calendar',
                    'active' => Request::is('calendar*') && !Request::is('calendar/timesheets*'),
                    'nested' => false,
                ])
            @endif
@php $sb['calendar'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
@if($calendarGate && $showTimesheets)
                @include('partials.ess-nav-item', [
                    'href'   => route('calendar.timesheets'),
                    'icon'   => 'fas fa-clock',
                    'label'  => 'My Timesheet',
                    'active' => Request::is('calendar/timesheets*'),
                    'nested' => false,
                ])
            @endif
@php $sb['timesheet'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
{{-- My Approvals: shown to anyone NAMED on an approval workflow (any module) — they need no HR menu to approve. --}}
@if(session('user.type') === 'employee' && app(\App\Services\Approval\ApprovalEngine::class)->isApproverAnywhere((int) session('user.id')))
                @include('partials.ess-nav-item', [
                    'href'   => route('general.approvals.index'),
                    'icon'   => 'fas fa-clipboard-check',
                    'label'  => 'My Approvals',
                    'active' => Request::is('general/approvals*'),
                    'nested' => false,
                ])
            @endif
@php $sb['approvals'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

            @if($can('reporting'))
                @php
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
                    @if($canRepProject)

                    <div>
                        <button onclick="toggleReportingProjectDropdown()" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ $repProjectActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-project-diagram text-xs"></i>
                            </span>
                            <span class="nav-text text-sm flex-1">Project Reports</span>
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

                    <div>
                        <button onclick="toggleReportingSupportDropdown()" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ $repSupportActive ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-headset text-xs"></i>
                            </span>
                            <span class="nav-text text-sm flex-1">Support Reports</span>
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
                                <span class="nav-text text-sm">Ticket by Module</span>
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
            @endif
@php $sb['reporting'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
@if($can('master') && $can('master.employee'))
                @include('partials.ess-nav-item', [
                    'href'   => route('master.employee.index'),
                    'icon'   => 'fas fa-users',
                    'label'  => 'Employee Data',
                    'active' => Request::is('master/employee*'),
                    'nested' => false,
                ])
            @endif
@php $sb['employee'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
@if($can('master') && $can('master.customer'))
                @include('partials.ess-nav-item', [
                    'href'   => route('master.customer.index'),
                    'icon'   => 'fas fa-user-tie',
                    'label'  => 'Business Partner',
                    'active' => Request::is('master/customer*'),
                    'nested' => false,
                ])
            @endif
@php $sb['partner'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('general.onboarding'))

                    <div class="mb-2">
                        <a href="{{ route('general.onboarding.index') }}"
                            class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/onboarding*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                <i class="fas fa-user-check"></i>
                            </span>
                            <span class="nav-text font-medium">Onboarding</span>
                        </a>
                    </div>
            @endif
@php $sb['onboarding'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

            @if($can('general.contracts.list') || $can('general.contracts.templates'))
                <div class="mb-2">
                    <a href="{{ route('general.contracts.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/contracts*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-file-contract"></i>
                        </span>
                        <span class="nav-text font-medium">Contract</span>
                    </a>
                </div>
            @endif
@php $sb['contracts'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            {{-- Offboarding — daftar + Settings (two tabs, two slugs); opens the first tab this person holds. --}}
            @if($can('general.offboarding.list') || $can('general.offboarding.settings'))
                @include('partials.ess-nav-item', ['href' => $can('general.offboarding.list') ? route('general.offboarding.index') : route('general.offboarding.settings.index'), 'icon' => 'fas fa-user-minus', 'label' => 'Offboarding', 'active' => Request::is('general/offboarding*'), 'nested' => false])
            @endif
@php $sb['offboarding'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            {{-- Business & Legal → Legal: ONE item for the Legal summary (dashboard) and the Legal Basis register (tabs of the same page). --}}
            @if($can('legal') || $can('finance.legal-basis'))
                @include('partials.ess-nav-item', ['href' => $can('legal') ? route('legal') : route('finance.legal-basis.index'), 'icon' => 'fas fa-scale-balanced', 'label' => 'Legal Basis', 'active' => Request::is('legal', 'legal/basis*'), 'nested' => false])
            @endif
@php $sb['legal_basis'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            {{-- External (konsultan eksternal) — six pages, each with its own Menu Access slug; the section shows only when one passes. --}}
            @foreach([
                ['general.external.consultants', 'general.external.consultants.index', 'general/external/consultants*', 'fas fa-user-tie', 'External Consultants'],
                ['general.external.timesheet', 'general.external.timesheet.index', 'general/external/timesheet*', 'fas fa-clock', 'Timesheet & Payment'],
                ['general.external.contracts', 'general.external.contracts.index', 'general/external/contracts*', 'fas fa-file-contract', 'External Contract'],
                ['general.external.assignment-letters', 'general.external.assignment-letters.index', 'general/external/assignment-letters*', 'fas fa-briefcase', 'Assignment Letter'],
                ['general.external.employment-certificates', 'general.external.employment-certificates.index', 'general/external/employment-certificates*', 'fas fa-file-circle-check', 'Employment Certificate'],
                ['general.external.reference-letters', 'general.external.reference-letters.index', 'general/external/reference-letters*', 'fas fa-file-signature', 'Reference Letter'],
            ] as [$extSlug, $extRoute, $extActive, $extIcon, $extLabel])
                @php
                    // Timesheet & Payment has three tabs with their own slugs: the entry opens the first tab this person holds.
                    $extHas = $can($extSlug) || ($extSlug === 'general.external.timesheet' && ($can('general.external.timesheet.invoices') || $can('general.external.timesheet.settings')));
                    if ($extSlug === 'general.external.timesheet' && !$can($extSlug)) {
                        $extRoute = $can('general.external.timesheet.invoices') ? 'general.external.timesheet.invoices' : 'general.external.timesheet.settings.index';
                    }
                @endphp
                @if($extHas)
                    @include('partials.ess-nav-item', ['href' => route($extRoute), 'icon' => $extIcon, 'label' => $extLabel, 'active' => Request::is($extActive), 'nested' => false])
                @endif
            @endforeach
@php $sb['external'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            {{-- My Timesheet & Invoice — the external consultant's own page (role External Consultant); internal staff keep My Timesheet. --}}
            @if($can('general.external.my-timesheet'))
                @include('partials.ess-nav-item', ['href' => route('general.external.my-timesheet.index'), 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'My Timesheet & Invoice', 'active' => Request::is('general/external/my-timesheet*'), 'nested' => false])
            @endif
@php $sb['my_ext_timesheet'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            {{-- Commercial & Finance → PPh 21 (Payroll Fase 1) — opens its first tab (Settings). --}}
            @if($can('finance.pph21.settings') || $can('finance.pph21.report'))
                <div class="mb-2">
                    <a href="{{ route('finance.pph21.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('finance/pph21*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-percent"></i>
                        </span>
                        <span class="nav-text font-medium">PPh 21</span>
                    </a>
                </div>
            @endif
@php $sb['pph21'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

            @if($can('finance.bpjs.settings') || $can('finance.bpjs.report') || $can('finance.bpjs.letters'))
                <div class="mb-2">
                    <a href="{{ route('finance.bpjs.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('finance/bpjs*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-heart-pulse"></i>
                        </span>
                        <span class="nav-text font-medium">BPJS</span>
                    </a>
                </div>
            @endif
@php $sb['bpjs'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

            @if($can('general.inventory.overview') || $can('general.inventory.items') || $can('general.inventory.assets') || $can('general.inventory.settings'))
                <div class="mb-2">
                    <a href="{{ route('general.inventory.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/inventory*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-boxes-stacked"></i>
                        </span>
                        <span class="nav-text font-medium">Inventory & Assets</span>
                    </a>
                </div>
            @endif
@php $sb['inventory'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

            @if($can('finance.payroll.periods') || $can('finance.payroll.settings') || $can('finance.payroll.simulation'))
                <div class="mb-2">
                    <a href="{{ route('finance.payroll.index') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('finance/payroll*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-money-check-dollar"></i>
                        </span>
                        <span class="nav-text font-medium">Payroll</span>
                    </a>
                </div>
            @endif
@php $sb['payroll'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
                        @if($can('hr_general.leave_permit') || $can('general'))
                            <a href="{{ route('hr-general.leave-permit') }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('hr-general/leave-permit*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-calendar-minus"></i>
                                </span>
                                <span class="nav-text font-medium">Leave & Permit</span>
                            </a>
                        @endif
@php $sb['leave'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @php
                            $attendanceGate = $can('general.attendance')
                                || $can('general.attendance.monthly')
                                || $can('general.attendance.correction')
                                || $can('general.settings.shifts')
                                || $can('general.settings.attendance')
                                || $can('general');

                            $attendanceLanding = match (true) {
                                $can('general.attendance') || $can('general')            => route('general.attendance.daily'),
                                $can('general.attendance.monthly')                       => route('general.attendance.monthly'),
                                $can('general.attendance.correction')                    => route('general.attendance.corrections.index'),
                                $can('general.settings.shifts')                          => route('general.attendance.shifts.index'),
                                $can('general.settings.attendance')                      => route('general.attendance.settings.edit'),
                                default                                                  => route('general.attendance.daily'),
                            };
                        @endphp
                        @if($attendanceGate)
                            <a href="{{ $attendanceLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/attendance*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-clipboard-list"></i>
                                </span>
                                <span class="nav-text font-medium">Attendance</span>
                            </a>
                        @endif
@php $sb['attendance'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @php
                            $overtimeGate = $can('general.overtime') || $can('general.settings.overtime') || $can('general.approval-workflow.overtime') || $can('general')
                                || app(\App\Services\Overtime\OvertimeService::class)->isDesignatedApprover((int) session('user.id'));
                            $overtimeLanding = ($can('general.overtime') || $can('general')
                                    || app(\App\Services\Overtime\OvertimeService::class)->isDesignatedApprover((int) session('user.id')))
                                ? route('general.overtime.index')
                                : route('general.overtime.settings.edit');
                        @endphp
                        @if($overtimeGate)
                            <a href="{{ $overtimeLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/overtime*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-clock"></i>
                                </span>
                                <span class="nav-text font-medium">Overtime Management</span>
                            </a>
                        @endif
@php $sb['overtime'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @php
                            $reimbursementGate = $can('general.reimbursement') || $can('general.settings.reimbursement') || $can('general.approval-workflow.reimbursement') || $can('general');
                            $reimbursementLanding = ($can('general.reimbursement') || $can('general'))
                                ? route('general.reimbursement.index')
                                : route('general.reimbursement.settings.edit');
                        @endphp
                        @if($reimbursementGate)
                            <a href="{{ $reimbursementLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/reimbursement*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-receipt"></i>
                                </span>
                                <span class="nav-text font-medium">Reimbursement Management</span>
                            </a>
                        @endif
@php $sb['reimbursement'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @php
                            $purchaseRequestGate = $can('general.purchase-request') || $can('general.purchase-order') || $can('general.settings.purchase-request') || $can('general.approval-workflow.purchase-request') || $can('general');
                            $purchaseRequestLanding = ($can('general.purchase-request') || $can('general'))
                                ? route('general.purchase-request.index')
                                : ($can('general.purchase-order')
                                    ? route('general.purchase-order.index')
                                    : route('general.purchase-request.settings.edit'));
                        @endphp
                        @if($purchaseRequestGate)
                            <a href="{{ $purchaseRequestLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ (Request::is('general/purchase-request*') || Request::is('general/purchase-order*')) ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-cart-shopping"></i>
                                </span>
                                <span class="nav-text font-medium">Purchase Management</span>
                            </a>
                        @endif
@php $sb['purchase_management'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @if($can('general.cash-advance-dashboard') || $can('general.cash-advance') || $can('general.cash-advance-report') || $can('management.cash-advance-settings') || $can('management.approval-workflow.cash-advance') || $can('management.approval-workflow.cash-advance-report') || $can('general'))

                            @php
                                // Dashboard · Cash Advance (CA) · Cash Advance Report (CAR) · Settings are tabs of this one entry.
                                $hrCaActive = Request::is('general/cash-advance')
                                    || Request::is('general/cash-advance/*')
                                    || Request::is('general/cash-advance-report*')
                                    || Request::is('management/cash-advance-settings*');
                                $cashAdvanceLanding = match (true) {
                                    $can('general.cash-advance-dashboard')           => route('general.cash-advance.dashboard'),
                                    $can('general.cash-advance') || $can('general')  => route('general.cash-advance.index'),
                                    $can('general.cash-advance-report')              => route('general.cash-advance-report.index'),
                                    default                                          => route('management.cash-advance-settings.edit'),
                                };
                                // What waits for this person, CA and CAR together.
                                $caWaiting = ($can('general.cash-advance') || $can('general'))
                                    ? count(app(\App\Services\CashAdvance\CashAdvanceService::class)->pendingIdsFor((int) session('user.id'))) : 0;
                                $carWaiting = ($can('general.cash-advance-report') || $can('general'))
                                    ? count(app(\App\Services\CashAdvance\CashAdvanceReportService::class)->pendingIdsFor((int) session('user.id'))) : 0;
                                $caPending = $caWaiting + $carWaiting;
                            @endphp
                            <a href="{{ $cashAdvanceLanding }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ $hrCaActive ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </span>
                                <span class="nav-text font-medium flex-1">Cash Advance Management</span>
                                @if($caPending > 0)
                                    <span class="nav-text sb-badge">{{ $caPending > 99 ? '99+' : $caPending }}</span>
                                @endif
                            </a>
                        @endif
@php $sb['cash_advance'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
                        @if($can('general.kpi-evaluation') || $can('general'))
                            <a href="{{ route('general.kpi-evaluation.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/kpi-evaluation*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-chart-bar"></i>
                                </span>
                                <span class="nav-text font-medium">KPI Evaluation</span>
                            </a>
                        @endif
@php $sb['kpi'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @php
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
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ $recruitmentActive ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-user-tie"></i>
                                </span>
                                <span class="nav-text font-medium">Recruitment</span>
                            </a>
                        @endif
@php $sb['recruitment'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

                        @if($can('general.recruitment.offers') || $can('general.recruitment.offers.settings'))
                            <a href="{{ $can('general.recruitment.offers') ? route('general.recruitment.offers.index') : route('general.recruitment.offers.settings.edit') }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/recruitment/offers*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-file-signature"></i>
                                </span>
                                <span class="nav-text font-medium flex-1">Offering Letter</span>
                                @php $pendingOffers = $can('general.recruitment.offers') ? \App\Models\Recruitment\Offer::where('decision', 'pending')->count() : 0; @endphp
                                @if($pendingOffers > 0)
                                    <span class="nav-text sb-badge">
                                        {{ $pendingOffers > 99 ? '99+' : $pendingOffers }}
                                    </span>
                                @endif
                            </a>
                        @endif

                        @if($can('general.letters.dashboard') || $can('general.letters.requests') || $can('general.letters.register')
                            || $can('general.letters.compose') || $can('general.letter-templates'))
                            <a href="{{ route('general.letters.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general/letters*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                    <i class="fas fa-file-lines"></i>
                                </span>
                                <span class="nav-text font-medium">Letter Templates</span>
                            </a>
                        @endif
@php $sb['letter_templates'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('business'))

                <div class="mb-2">
                    <a href="{{ route('business') }}"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('business') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-briefcase"></i>
                        </span>
                        <span class="nav-text font-medium">Business Dev</span>
                    </a>
                </div>
            @endif
@php $sb['business'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('tickets.inbox'))

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
@php $sb['ticket'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('ticket.my-tasks'))

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
@php $sb['my_tasks'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('ticket.consultant-workload'))

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
@php $sb['workload'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('tickets.staging'))

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
                            class="nav-text sb-badge {{ $unvalidatedCount > 0 ? '' : 'hidden' }}">
                            {{ $unvalidatedCount > 99 ? '99+' : $unvalidatedCount }}
                        </span>
                    </a>
                </div>
            @endif
@php $sb['validation'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('delivery'))

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
@php $sb['delivery'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @if($can('control-center'))

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
@php $sb['control_center'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @php
                $showSlaMenu = isset($showSlaMenu) ? $showSlaMenu : $can('sla');
                $canManageSla = isset($canManageSla) ? $canManageSla : $can('sla.config');
            @endphp
            @if($showSlaMenu || $canManageSla)

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
@php $sb['sla'] = ob_get_clean(); @endphp
@php ob_start(); @endphp
            @php
                $showRpmoMenu = isset($showRpmoMenu) ? $showRpmoMenu : ($can('rpmo') || $can('rpmo.overview'));
            @endphp
            @if($showRpmoMenu)

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
                                class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
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
@php $sb['rpmo'] = ob_get_clean(); @endphp
@php ob_start(); @endphp

            @if($can('management'))

                <div class="mb-2">
                    <button onclick="toggleManajemenDropdown()"
                        class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ (Request::is('management*') && !Request::is('management/cash-advance-settings*')) ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-user-shield"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Management</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="manajemenChevron"></i>
                    </button>
                    <div id="manajemenDropdown"
                        class="nav-text {{ (Request::is('management*') && !Request::is('management/cash-advance-settings*')) ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
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
                                <span class="nav-text text-sm">Menu List</span>
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
                        @if($can('management.branches'))
                            <a href="{{ route('management.branches.index') }}"
                                class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('management/branches*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                    <i class="fas fa-building text-xs"></i>
                                </span>
                                <span class="nav-text text-sm">Branches</span>
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

                        @if($can('management.employee'))
                            <div class="mt-1">
                                <button onclick="toggleMasterMgmtDropdown()"
                                    class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg w-full text-left {{ Request::is('management/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                    <span class="nav-icon w-4 h-4 flex items-center justify-center">
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
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-id-card text-xs"></i></span>
                                            <span class="nav-text text-xs">Basic Data</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.address'))
                                        <a href="{{ route('management.employee.address.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/address*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-map-marker-alt text-xs"></i></span>
                                            <span class="nav-text text-xs">Address</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.identification'))
                                        <a href="{{ route('management.employee.identification.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/identification*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-fingerprint text-xs"></i></span>
                                            <span class="nav-text text-xs">Identification</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.family'))
                                        <a href="{{ route('management.employee.family.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/family*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-users text-xs"></i></span>
                                            <span class="nav-text text-xs">Family</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.education'))
                                        <a href="{{ route('management.employee.education.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/education*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-graduation-cap text-xs"></i></span>
                                            <span class="nav-text text-xs">Education</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.qualification'))
                                        <a href="{{ route('management.employee.qualification.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/qualification*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-certificate text-xs"></i></span>
                                            <span class="nav-text text-xs">Qualification</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.contract'))
                                        <a href="{{ route('management.employee.contract.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/contract*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-file-contract text-xs"></i></span>
                                            <span class="nav-text text-xs">Contract</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.bank'))
                                        <a href="{{ route('management.employee.bank.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/bank*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-university text-xs"></i></span>
                                            <span class="nav-text text-xs">Bank Account</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.payment'))
                                        <a href="{{ route('management.employee.payment.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/payment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
                                                    class="fas fa-money-bill text-xs"></i></span>
                                            <span class="nav-text text-xs">Basic Payment</span>
                                        </a>
                                    @endif
                                    @if($can('management.employee.attachment'))
                                        <a href="{{ route('management.employee.attachment.index') }}"
                                            class="nav-link flex items-center gap-3 px-4 py-2 rounded-lg {{ Request::is('management/employee/attachment*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i
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
@php $sb['management'] = ob_get_clean(); @endphp

            @php
                $sbSections = [
                    ['id' => 'Workspace', 'title' => 'My Workspace',        'icon' => 'smart-home', 'keys' => ['ess_workspace', 'approvals', 'timesheet', 'calendar']],
                    ['id' => 'Hc',        'title' => 'Human Capital',       'icon' => 'address-book', 'keys' => ['employee', 'recruitment', 'offering', 'onboarding', 'offboarding', 'attendance', 'leave', 'overtime', 'kpi', 'letter_templates', 'inventory']],
                    ['id' => 'External',  'title' => 'External',            'icon' => 'briefcase', 'keys' => ['external']],
                    ['id' => 'Finance',   'title' => 'Finance',  'icon' => 'wallet', 'keys' => ['reimbursement', 'purchase_management', 'cash_advance', 'payroll', 'bpjs', 'pph21']],
                    ['id' => 'Work',      'title' => 'Work & Service',      'icon' => 'tools', 'keys' => ['ticket', 'my_tasks', 'workload', 'validation', 'delivery', 'sla', 'rpmo']],
                    ['id' => 'Ai',        'title' => 'AI Tools',            'icon' => 'sparkles', 'keys' => ['ess_tools']],
                    ['id' => 'Business',  'title' => 'Business & Legal',    'icon' => 'building-skyscraper', 'keys' => ['legal_basis', 'contracts', 'partner', 'business']],
                    ['id' => 'Reporting', 'title' => 'Reporting',           'icon' => 'chart-histogram', 'keys' => ['reporting']],
                    ['id' => 'Admin',     'title' => 'Administration',      'icon' => 'shield-cog', 'keys' => ['management', 'control_center']],
                ];
            @endphp

            <div class="sb-sec-body sb-solo">{!! \App\Support\SidebarIcons::apply($sb['dashboard'] ?? '') !!}</div>

            @foreach($sbSections as $sbSec)
                @php $sbBody = \App\Support\SidebarIcons::apply(implode("\n", array_map(fn ($k) => $sb[$k] ?? '', $sbSec['keys']))); @endphp
                @if(str_contains($sbBody, 'nav-link'))
                    @include('partials.sidebar-section', ['id' => $sbSec['id'], 'title' => $sbSec['title'], 'icon' => $sbSec['icon'], 'body' => $sbBody])
                @endif
            @endforeach

            <div class="my-6 border-t border-white border-opacity-10"></div>
            @php ob_start(); @endphp
            <div class="sb-sec-body sb-solo">

            <div class="mb-2">
                <a href="{{ route('settings.index') }}"
                    class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('settings*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                    <span class="nav-icon w-5 h-5 flex items-center justify-center">
                        <i class="fas fa-cog"></i>
                    </span>
                    <span class="nav-text font-medium">Settings</span>
                </a>
            </div>
            </div>
            @php echo \App\Support\SidebarIcons::apply(ob_get_clean()); @endphp
        </nav>
    @endif
    </div>
    @unless($__env->hasSection('sidebar-nav'))

    <div class="sb-footer">
        <button type="button" id="sidebarLayoutToggle" class="sb-footer-btn" onclick="toggleSidebarLayout()"
            aria-pressed="false" title="Collapse to icons" aria-label="Collapse to icons">
            <svg class="sb-ico" aria-hidden="true" focusable="false"><use href="#ti-layout-sidebar-left-collapse"/></svg>
            <span class="sb-footer-label">Collapse to icons</span>
        </button>
    </div>
    @endunless
</aside>

<style>
    #sidebar #sidebarSearch {
        background-color: rgba(255, 255, 255, 0.12) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.18);
    }
    #sidebar #sidebarSearch::placeholder { color: rgba(255, 255, 255, 0.6) !important; }
    #sidebar #sidebarSearch::-webkit-search-cancel-button { display: none; }
    #sidebar .sb-hide { display: none !important; }
    #sidebar a.nav-link { position: relative; }
    #sidebar a.nav-link > .nav-text { min-width: 0; overflow-wrap: anywhere; }
    #sidebar .sb-pin {
        flex: 0 0 auto; margin-left: auto; padding: 2px 4px; border-radius: 6px; font-size: 12px; line-height: 1;
        color: rgba(255, 255, 255, 0.75); opacity: 0; cursor: pointer; transition: opacity .15s;
    }
    #sidebar a.nav-link:hover .sb-pin, #sidebar .sb-pin:focus, #sidebar .sb-pin.on { opacity: 1; }
    #sidebar .sb-pin.on { color: #fde047; }
    #sidebar .sb-pin:hover { background: rgba(255, 255, 255, 0.18); }
    @media (hover: none) { #sidebar .sb-pin { opacity: .55; } #sidebar .sb-pin.on { opacity: 1; } }
    html[data-sb-layout="rail"] #sidebar .sb-pin { display: none; }
    #sidebarScroll { scrollbar-width: thin; scrollbar-color: rgba(255, 255, 255, 0.35) transparent; }
    #sidebarScroll::-webkit-scrollbar { width: 6px; }
    #sidebarScroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.35); border-radius: 9999px; }
    #sidebar .sb-sec { margin-top: .5rem; padding-top: .5rem; border-top: 1px solid rgba(255, 255, 255, 0.08); }
    #sidebar .sb-sec-btn {
        display: flex; align-items: center; width: 100%; padding: .375rem 1rem; text-align: left;
        font-size: .6875rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
        color: rgba(255, 255, 255, 0.78); border-radius: .5rem; transition: color .15s, background-color .15s;
    }
    #sidebar .sb-sec-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }
    #sidebar .sb-sec-btn:focus-visible { outline: 2px solid rgba(255, 255, 255, 0.5); outline-offset: -2px; }
    #sidebar .sb-sec-btn .sb-sec-title { flex: 1; }
    #sidebar .sb-sec-btn i { font-size: .625rem; }
    #sidebar .sb-sec-body { margin-top: .125rem; }
    #sidebar .sb-sec-body > a.nav-link,
    #sidebar .sb-sec-body > div > a.nav-link,
    #sidebar .sb-sec-body > div > button.nav-link {
        padding-top: .5rem; padding-bottom: .5rem; border-radius: .5rem; font-size: .875rem; line-height: 1.25rem;
    }
    #sidebar .sb-sec-body > div.mb-2 { margin-bottom: .125rem; }
    #sidebar .sb-sec-body > a.nav-link { margin-bottom: .125rem; }
    #sidebar .sb-sec-body > a.nav-link .nav-icon,
    #sidebar .sb-sec-body > div > a.nav-link .nav-icon,
    #sidebar .sb-sec-body > div > button.nav-link .nav-icon { width: 1.25rem; height: 1.25rem; }
    #sidebar .sb-sec-body > a.nav-link .nav-icon i,
    #sidebar .sb-sec-body > div > a.nav-link .nav-icon i,
    #sidebar .sb-sec-body > div > button.nav-link .nav-icon i { font-size: .875rem; }
    #sidebar .sb-sec-body > a.nav-link.text-opacity-70,
    #sidebar .sb-sec-body > div > a.nav-link.text-opacity-70,
    #sidebar .sb-sec-body > div > button.nav-link.text-opacity-70 { --tw-text-opacity: .8; }
    #sidebar a.nav-link.active,
    #sidebar a.nav-link.bg-opacity-15 {
        position: relative; background-color: rgba(255, 255, 255, 0.24) !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16) !important;
    }
    #sidebar { --sb-accent: #f2c14e; }
    #sidebar a.nav-link.active::before,
    #sidebar a.nav-link.bg-opacity-15::before {
        content: ''; position: absolute; left: 0; top: 18%; bottom: 18%; width: 3px;
        border-radius: 0 3px 3px 0; background: var(--sb-accent);
    }
    #sidebar a.nav-link.active .nav-icon,
    #sidebar a.nav-link.bg-opacity-15 .nav-icon { color: var(--sb-accent); }
    #sidebar a.nav-link.active .nav-text,
    #sidebar a.nav-link.bg-opacity-15 .nav-text { font-weight: 600; }
    #sidebar button.nav-link.active { background-color: rgba(255, 255, 255, 0.11) !important; box-shadow: none !important; }
    #sidebar .sb-badge {
        background: #fde68a; color: #78350f; font-size: 10px; font-weight: 700; line-height: 1;
        padding: 3px 7px; border-radius: 9999px; min-width: 20px; text-align: center;
    }
    #sidebar .sb-dot {
        width: 6px; height: 6px; margin-right: .5rem; border-radius: 9999px; background: #fbbf24;
        box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.25); flex-shrink: 0;
    }
    #sidebar .sb-dot.hidden { display: none; }
    #sidebar .sb-toggle-all {
        flex-shrink: 0; width: 2rem; height: 2rem; border-radius: .5rem; font-size: .75rem;
        color: rgba(255, 255, 255, 0.6); transition: color .15s, background-color .15s;
    }
    #sidebar .sb-toggle-all:hover { color: #fff; background: rgba(255, 255, 255, 0.12); }
    #sidebar .sb-toggle-all:focus-visible { outline: 2px solid rgba(255, 255, 255, 0.5); outline-offset: -2px; }
    .sb-ico {
        display: block; flex-shrink: 0; width: 1.25rem; height: 1.25rem; fill: none; stroke: currentColor;
        stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round;
    }
    .nav-icon.w-3 > .sb-ico, .nav-icon.w-4 > .sb-ico { width: 1rem; height: 1rem; }
    .sb-sec-icon, .sb-rail-only { display: none; }
    .sb-footer { flex-shrink: 0; padding: .5rem .75rem; border-top: 1px solid rgba(255, 255, 255, 0.10); }
    .sb-footer-btn {
        display: flex; align-items: center; gap: .5rem; width: 100%; padding: .4rem .6rem; border-radius: .5rem;
        font-size: .75rem; color: rgba(255, 255, 255, 0.65); transition: color .15s, background-color .15s;
    }
    .sb-footer-btn:hover { color: #fff; background: rgba(255, 255, 255, 0.10); }
    .sb-footer-btn .sb-ico { width: 1.125rem; height: 1.125rem; }
    @media (max-width: 1023px) { .sb-footer { display: none; } }
    @media (min-width: 1024px) {
        html[data-sb-layout="rail"] #sidebar.w-64 { width: 5rem; overflow: visible; }
        html[data-sb-layout="rail"] #mainContent.lg\:ml-64 { margin-left: 5rem; }
        html[data-sb-layout="rail"] #sidebar #sidebarScroll { overflow: visible; }
        html[data-sb-layout="rail"] #sidebar #sidebarHeader { padding-left: .75rem; padding-right: .75rem; }
        html[data-sb-layout="rail"] #sidebar .sidebar-logo > div { width: 2.35rem; padding: 0; margin: 0 auto; overflow: hidden; }
        html[data-sb-layout="rail"] #sidebar .sidebar-logo img { width: 7.5rem; max-width: none; max-height: none; }
        html[data-sb-layout="rail"] #sidebar .sb-search-row { display: none; }
        html[data-sb-layout="rail"] #sidebar .sb-rail-only {
            display: flex; align-items: center; justify-content: center; width: 3rem; height: 2.5rem; margin: .75rem auto 0;
            border-radius: .75rem; color: rgba(255, 255, 255, 0.8); transition: background-color .15s;
        }
        html[data-sb-layout="rail"] #sidebar .sb-rail-only:hover { background: rgba(255, 255, 255, 0.12); color: #fff; }
        html[data-sb-layout="rail"] #sidebar .sb-solo .nav-text,
        html[data-sb-layout="rail"] #sidebar .sb-solo .sb-pin { display: none; }
        html[data-sb-layout="rail"] #sidebar .sb-solo a.nav-link { justify-content: center; padding-left: 0; padding-right: 0; }
        html[data-sb-layout="rail"] #sidebar .sb-sec { margin-top: .25rem; padding-top: .25rem; }
        html[data-sb-layout="rail"] #sidebar .sb-sec-btn {
            position: relative; justify-content: center; width: 3rem; height: 2.75rem; margin: 0 auto; padding: 0;
            border-radius: .75rem; color: rgba(255, 255, 255, 0.82);
        }
        html[data-sb-layout="rail"] #sidebar .sb-sec-icon { display: block; width: 1.4rem; height: 1.4rem; }
        html[data-sb-layout="rail"] #sidebar .sb-sec-title,
        html[data-sb-layout="rail"] #sidebar .sb-sec-btn > i { display: none; }
        html[data-sb-layout="rail"] #sidebar .sb-sec.has-active > .sb-sec-btn { background: rgba(255, 255, 255, 0.22); color: #fff; }
        html[data-sb-layout="rail"] #sidebar .sb-sec.rail-open > .sb-sec-btn { background: rgba(255, 255, 255, 0.16); color: #fff; }
        html[data-sb-layout="rail"] #sidebar .sb-sec.has-active > .sb-sec-btn::before {
            content: ''; position: absolute; left: -.5rem; top: 22%; bottom: 22%; width: 3px; border-radius: 0 3px 3px 0; background: var(--sb-accent, #f2c14e);
        }
        html[data-sb-layout="rail"] #sidebar .sb-dot { position: absolute; top: .45rem; right: .55rem; margin: 0; }
        html[data-sb-layout="rail"] #sidebar .sb-sec > .sb-sec-body { display: none !important; }
        html[data-sb-layout="rail"] #sidebar .sb-sec.rail-open > .sb-sec-body {
            display: block !important; position: absolute; left: calc(100% + .375rem); top: var(--fly-top, 0px); bottom: auto;
            width: 15.5rem; margin: 0; padding: .5rem; max-height: var(--fly-max, 80vh); overflow-y: auto; z-index: 60;
            background: var(--primary-surface); border: 1px solid rgba(255, 255, 255, 0.14);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.30); border-radius: .75rem;
        }
        html[data-sb-layout="rail"] #sidebar .sb-sec.rail-open > .sb-sec-body::before {
            content: attr(data-title); display: block; padding: .25rem .5rem .6rem; font-size: .6875rem; font-weight: 600;
            letter-spacing: .1em; text-transform: uppercase; color: rgba(255, 255, 255, 0.6);
        }
        html[data-sb-layout="rail"] #sidebar .sb-footer { padding: .5rem; }
        html[data-sb-layout="rail"] #sidebar .sb-footer-btn { justify-content: center; width: 3rem; margin: 0 auto; padding: .5rem 0; }
        html[data-sb-layout="rail"] #sidebar .sb-footer-label { display: none; }
    }
    #sidebar .sb-label {
        font-size: 10px; letter-spacing: .12em; text-transform: uppercase;
        color: rgba(255, 255, 255, 0.55); padding: 14px 16px 4px;
    }
</style>

<script>
    var SB_SEC_KEY = 'ecosystem:sidebar:sections:v1';
    function sbSecRead() { try { return JSON.parse(localStorage.getItem(SB_SEC_KEY)) || {}; } catch (e) { return {}; } }
    var SB_LAYOUT_KEY = 'ecosystem:sidebar:layout:v1';
    function sbIsRail() { return document.documentElement.getAttribute('data-sb-layout') === 'rail'; }
    function sbRailAllowed() { var a = document.getElementById('sidebar'); return !!a && a.getAttribute('data-rail') !== 'off'; }
    function sbCloseFlyouts(except) {
        sbSections().forEach(function (sec) {
            if (sec !== except && sec.classList.contains('rail-open')) {
                sec.classList.remove('rail-open');
                sec.querySelector('.sb-sec-btn').setAttribute('aria-expanded', 'false');
            }
        });
    }
    function sbToggleFlyout(id) {
        var sec = document.querySelector('#sidebar .sb-sec[data-sec="' + id + '"]');
        if (!sec) { return; }
        var open = !sec.classList.contains('rail-open');
        sbCloseFlyouts(open ? sec : null);
        sec.classList.toggle('rail-open', open);
        sec.querySelector('.sb-sec-btn').setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { sbPlaceFlyout(sec); }
    }
    function sbPlaceFlyout(sec) {
        var body = sec.querySelector('.sb-sec-body'), btn = sec.querySelector('.sb-sec-btn'), aside = document.getElementById('sidebar');
        if (!body || !btn || !aside) { return; }
        var margin = 12;
        var asideTop = aside.getBoundingClientRect().top, btnTop = btn.getBoundingClientRect().top - asideTop;
        var avail = Math.max(120, window.innerHeight - asideTop - margin);   // ruang vertikal total yang boleh dipakai panel
        // Tinggi isi yang sebenarnya (scrollHeight, bukan offsetHeight yang sudah terpotong max-height sebelumnya).
        body.style.setProperty('--fly-max', avail + 'px');
        var h = Math.min(body.scrollHeight, avail);
        // Panel menempel di ikon yang diklik dan MEMANJANG KE BAWAH; isi yang panjang digulir di dalam panel (bukan
        // menggeser panel ke atas sampai memenuhi tinggi layar). Hanya bila ruang di bawah ikon kurang dari tinggi
        // minimum yang nyaman dipakai (280 px, atau tinggi isinya bila lebih pendek), panel digeser ke atas seperlunya.
        var need = Math.min(h, 280);
        var top = (avail - btnTop >= need) ? btnTop : Math.max(0, avail - need);
        body.style.setProperty('--fly-top', top + 'px');
        // Batas tinggi = sisa ruang DI BAWAH posisi panel (bukan tinggi layar penuh), jadi tak pernah keluar layar.
        body.style.setProperty('--fly-max', (avail - top) + 'px');
    }
    // Tempatkan ulang saat isi panel berubah (membuka/menutup submenu di dalamnya) dan saat jendela diubah ukurannya.
    function sbReplaceOpenFlyout() {
        var sec = document.querySelector('#sidebar .sb-sec.rail-open');
        if (sec) { sbPlaceFlyout(sec); }
    }
    (function () {
        var queued = false;
        function queue() { if (queued) { return; } queued = true; requestAnimationFrame(function () { queued = false; sbReplaceOpenFlyout(); }); }
        window.addEventListener('resize', queue);
        var aside = document.getElementById('sidebar');
        if (aside && window.MutationObserver) {
            // Hanya perubahan class/anak (buka-tutup submenu); perubahan style (--fly-*) sengaja tidak diamati agar tak berulang.
            new MutationObserver(queue).observe(aside, { subtree: true, attributes: true, attributeFilter: ['class'], childList: true });
        }
        if (aside) { aside.addEventListener('click', function () { setTimeout(queue, 220); }); }   // setelah transisi submenu
    })();
    function sbApplyLayout(rail, persist) {
        if (rail && !sbRailAllowed()) { rail = false; }
        if (rail) { document.documentElement.setAttribute('data-sb-layout', 'rail'); }
        else { document.documentElement.removeAttribute('data-sb-layout'); }
        sbCloseFlyouts();
        sbSections().forEach(function (sec) {
            var btn = sec.querySelector('.sb-sec-btn');
            var title = sec.querySelector('.sb-sec-title');
            if (rail) { btn.title = title ? title.textContent.trim() : ''; btn.setAttribute('aria-expanded', 'false'); }
            else {
                btn.removeAttribute('title');
                btn.setAttribute('aria-expanded', sec.querySelector('.sb-sec-body').classList.contains('hidden') ? 'false' : 'true');
            }
        });
        document.querySelectorAll('#sidebar .sb-solo a.nav-link').forEach(function (a) {
            if (rail) { var t = a.querySelector('.nav-text'); a.title = t ? t.textContent.trim() : ''; }
            else { a.removeAttribute('title'); }
        });
        var tg = document.getElementById('sidebarLayoutToggle');
        if (tg) {
            var label = rail ? 'Expand sidebar' : 'Collapse to icons';
            tg.title = label; tg.setAttribute('aria-label', label); tg.setAttribute('aria-pressed', rail ? 'true' : 'false');
            var lab = tg.querySelector('.sb-footer-label'); if (lab) { lab.textContent = label; }
            var use = tg.querySelector('use'); if (use) { use.setAttribute('href', rail ? '#ti-layout-sidebar-left-expand' : '#ti-layout-sidebar-left-collapse'); }
        }
        if (persist) { try { localStorage.setItem(SB_LAYOUT_KEY, rail ? 'rail' : 'full'); } catch (e) { /* tak diingat saja */ } }
        sbRefreshSections();
    }
    function toggleSidebarLayout() { sbApplyLayout(!sbIsRail(), true); }
    function sbRailSearch() {
        sbApplyLayout(false, true);
        var i = document.getElementById('sidebarSearch'); if (i) { i.focus(); }
    }
    // Choosing a page collapses the sidebar to icons — but only AFTER the chosen page has opened: the click just leaves
    // a short-lived note, and the next page folds the sidebar with the normal width transition once it has loaded.
    // (Collapsing at click time would animate a sidebar that is about to be thrown away with the old page.)
    // Desktop only; section toggles are buttons, not links, so they never leave the note.
    var SB_AFTER_NAV_KEY = 'ecosystem:sidebar:collapse-after-nav';
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('#sidebar a.nav-link[href]') : null;
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        if (a.target === '_blank' || /^(#|javascript:)/i.test(a.getAttribute('href') || '')) { return; }
        if (window.innerWidth < 1024 || !sbRailAllowed() || sbIsRail()) { return; }
        try { sessionStorage.setItem(SB_AFTER_NAV_KEY, String(Date.now())); } catch (err) { /* no note: stays expanded */ }
    });
    function sbCollapseAfterNavigation() {
        var at = 0;
        try { at = parseInt(sessionStorage.getItem(SB_AFTER_NAV_KEY), 10) || 0; sessionStorage.removeItem(SB_AFTER_NAV_KEY); } catch (err) { return; }
        // The note is for the page the click opened: a stale one (page never arrived, tab left idle) is ignored.
        if (!at || Date.now() - at > 15000 || window.innerWidth < 1024 || !sbRailAllowed() || sbIsRail()) { return; }
        var fold = function () { setTimeout(function () { if (!sbIsRail()) { sbApplyLayout(true, true); } }, 250); };
        if (document.readyState === 'complete') { fold(); } else { window.addEventListener('load', fold, { once: true }); }
    }
    document.addEventListener('click', function (e) { if (sbIsRail() && !(e.target.closest && e.target.closest('#sidebar'))) { sbCloseFlyouts(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sbIsRail()) { sbCloseFlyouts(); } });

    function toggleSidebarSection(id) {
        if (sbIsRail()) { sbToggleFlyout(id); return; }
        var body = document.getElementById('sbSec' + id + 'Section');
        if (!body) { return; }
        body.classList.toggle('hidden');
        try {
            var c = sbSecRead();
            if (body.classList.contains('hidden')) { c[id] = 1; } else { delete c[id]; }
            localStorage.setItem(SB_SEC_KEY, JSON.stringify(c));
        } catch (e) { /* penyimpanan dinonaktifkan: lipat tetap bekerja, hanya tak diingat */ }
    }
    function sbSections() { return Array.prototype.slice.call(document.querySelectorAll('#sidebar .sb-sec')); }
    function sbRefreshSections() {
        var anyOpen = false;
        sbSections().forEach(function (sec) {
            var body = sec.querySelector('.sb-sec-body');
            var closed = body.classList.contains('hidden');
            if (!closed) { anyOpen = true; }
            var dot = sec.querySelector('.sb-dot');
            if (dot) { dot.classList.toggle('hidden', !((closed || sbIsRail()) && body.querySelector('.sb-badge:not(.hidden)'))); }
            sec.classList.toggle('has-active', !!body.querySelector('a.nav-link.active, a.nav-link.bg-opacity-15'));
        });
        var btn = document.getElementById('sidebarToggleAll');
        if (btn) {
            var label = anyOpen ? 'Collapse all sections' : 'Expand all sections';
            btn.setAttribute('aria-label', label);
            btn.title = label;
            var i = btn.querySelector('i');
            if (i) { i.className = 'fas ' + (anyOpen ? 'fa-angles-up' : 'fa-angles-down'); }
        }
    }
    function toggleAllSidebarSections() {
        var secs = sbSections();
        var collapse = secs.some(function (s) { return !s.querySelector('.sb-sec-body').classList.contains('hidden'); });
        var c = {};
        secs.forEach(function (sec) {
            sec.querySelector('.sb-sec-body').classList.toggle('hidden', collapse);
            if (collapse) { c[sec.getAttribute('data-sec')] = 1; }
        });
        try { localStorage.setItem(SB_SEC_KEY, JSON.stringify(c)); } catch (e) { /* tak diingat saja */ }
        var sc = document.getElementById('sidebarScroll');
        if (collapse && sc) { sc.scrollTop = 0; }
    }
    (function () {
        var c = sbSecRead();
        Object.keys(c).forEach(function (id) {
            var body = document.getElementById('sbSec' + id + 'Section');
            if (body && !body.querySelector('a.nav-link.active, a.nav-link.bg-opacity-15')) { body.classList.add('hidden'); }
        });
        sbApplyLayout(sbIsRail(), false); // tooltip/tombol footer sesuai mode yang sudah dipasang lebih awal
        sbCollapseAfterNavigation();
        if (window.MutationObserver) {
            var ob = new MutationObserver(sbRefreshSections);
            document.querySelectorAll('#sidebar .sb-sec-body, #sidebar .sb-badge').forEach(function (el) {
                ob.observe(el, { attributes: true, attributeFilter: ['class'] });
            });
        }
    })();
</script>

<script>
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

            function linkMatches(a) {
                if (has(a)) { return true; }
                for (var p = a.parentElement; p && p !== scroll; p = p.parentElement) {
                    var prev = p.previousElementSibling;
                    if (prev && prev.matches && prev.matches('button.nav-link, button.sb-sec-btn') && has(prev)) { return true; }
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

            scroll.querySelectorAll('button.nav-link').forEach(function (b) {
                var box = b.nextElementSibling;
                if (box && !box.querySelector('a.nav-link:not(.sb-hide)')) { b.classList.add('sb-hide'); }
            });

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

    (function () {
        var scroll = document.getElementById('sidebarScroll');
        if (!scroll || !window.__sidebarFavoritesEnabled) { return; }

        var MAX = window.__sidebarFavoritesMax || 6;
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

        function makePin(on) {
            var s = document.createElement('span');
            s.className = 'sb-pin' + (on ? ' on' : '');
            s.setAttribute('role', 'button');
            s.setAttribute('tabindex', '0');
            var label = on ? 'Unpin from sidebar' : 'Pin to sidebar';
            s.setAttribute('aria-label', label);
            s.title = label;
            var i = document.createElement('i');
            i.className = 'fas fa-thumbtack';
            s.appendChild(i);
            return s;
        }

        function existsInSidebar(path) {
            return links.some(function (a) { return pathOf(a) === path; });
        }

        function render() {
            links.forEach(function (a) {
                var old = a.querySelector('.sb-pin');
                if (old) { old.remove(); }
                a.appendChild(makePin(favs.indexOf(pathOf(a)) !== -1));
            });

            window.dispatchEvent(new CustomEvent('sidebar:favorites-changed', { detail: { favorites: favs.slice() } }));
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

        function pinClick(e) {
            var pin = e.target.closest ? e.target.closest('.sb-pin') : null;
            if (!pin) { return; }
            var a = pin.closest('a.nav-link');
            var path = a ? pathOf(a) : null;
            e.preventDefault();
            e.stopPropagation();
            if (path) { toggleFavorite(path); }
        }
        var sidebar = document.getElementById('sidebar');
        sidebar.addEventListener('click', pinClick, true);
        sidebar.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('sb-pin')) { pinClick(e); }
        }, true);

        window.SidebarFavorites = {
            toggle: toggleFavorite,
            list: function () { return favs.slice(); },
            MAX: MAX
        };

        render();
    })();

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

        var open = state.open || {};
        Object.keys(open).forEach(function (dropdownId) {
            var box = document.getElementById(dropdownId);
            if (!box) { return; }
            box.classList.remove('hidden');
            var chevron = document.getElementById(open[dropdownId]);
            if (chevron && !chevron.classList.contains('rotate-180')) { chevron.classList.add('rotate-180'); }
        });

        if (typeof state.scroll === 'number') { scroll.scrollTop = state.scroll; }

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

        function chevronOf(box) {
            var btn = box.previousElementSibling;
            var ch = btn && btn.querySelector ? btn.querySelector('[id$="Chevron"]') : null;
            return ch ? ch.id : null;
        }
        var boxes = scroll.querySelectorAll('[id$="Dropdown"], [id$="Submenu"]');

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
    })();
</script>

<script>
    function toggleSidebarDropdown(dropdownId) {
        var box = document.getElementById(dropdownId);
        if (box) { box.classList.toggle('hidden'); }
    }

    function toggleReportingProjectDropdown() { toggleSidebarDropdown('reportingProjectDropdown'); }
    function toggleReportingSupportDropdown() { toggleSidebarDropdown('reportingSupportDropdown'); }
    function toggleDeliveryDropdown() { toggleSidebarDropdown('deliveryDropdown'); }
    function toggleAdminDropdown() { toggleSidebarDropdown('adminDropdown'); }
    function toggleSlaDropdown() { toggleSidebarDropdown('slaDropdown'); }
    function toggleRpmoDropdown() { toggleSidebarDropdown('rpmoSubmenu'); }
    function toggleManajemenDropdown() { toggleSidebarDropdown('manajemenDropdown'); }
    function toggleEssGroupDropdown(groupId) { toggleSidebarDropdown('essGroup' + groupId + 'Dropdown'); }
    function toggleMasterMgmtDropdown() { toggleSidebarDropdown('masterMgmtDropdown'); }

    (function () {
        var scroll = document.getElementById('sidebarScroll');
        if (!scroll) { return; }

        function sync(box) {
            var btn = box.previousElementSibling;
            if (!btn || !btn.querySelector) { return; }
            var open = !box.classList.contains('hidden');
            var chevron = btn.querySelector('[id$="Chevron"]');
            if (chevron) { chevron.classList.toggle('rotate-180', open); }
            if (btn.tagName === 'BUTTON') { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        }

        var boxes = scroll.querySelectorAll('[id$="Dropdown"], [id$="Submenu"], [id$="Section"]');
        boxes.forEach(sync); // keadaan awal (dirender Blade / dipulihkan dari sessionStorage)
        if (window.MutationObserver) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) { sync(m.target); });
            });
            boxes.forEach(function (box) { observer.observe(box, { attributes: true, attributeFilter: ['class'] }); });
        }
    })();
</script>

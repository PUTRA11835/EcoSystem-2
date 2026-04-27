<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-data" content='@json(session("user"))'>
    <title>@yield('title', 'Dashboard') - EcoSystem</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    @php
        $preferences = session('user_preferences', [
            'theme' => 'light',
            'primary_color' => '#991b1b',
            'sidebar_style' => 'gradient',
            'font_size' => 'medium',
            'compact_mode' => false,
            'show_animations' => true,
        ]);
        
        // Convert hex to RGB for Tailwind
        $primaryColor = $preferences['primary_color'];
        $rgb = sscanf($primaryColor, "#%02x%02x%02x");
        $primaryRgb = implode(', ', $rgb);
        
        // Calculate darker shade
        $darkR = max(0, $rgb[0] - 40);
        $darkG = max(0, $rgb[1] - 40);
        $darkB = max(0, $rgb[2] - 40);
        $primaryDarkRgb = "$darkR, $darkG, $darkB";
        
        // Font sizes
        $fontSizes = [
            'small' => '14px',
            'medium' => '16px',
            'large' => '18px'
        ];
        $baseFontSize = $fontSizes[$preferences['font_size']];
        
        // Theme colors
        $bgColor = $preferences['theme'] === 'dark' ? '#111827' : '#f9fafb';
        $textColor = $preferences['theme'] === 'dark' ? '#f9fafb' : '#111827';
        $cardBg = $preferences['theme'] === 'dark' ? '#1f2937' : '#ffffff';
        
        // Get user role_id from session
        $user = session('user', []);
        $userRoleId = $user['role']['id'] ?? 1;
        
        // Define menu visibility based on role
        $showAllMenus   = $userRoleId == 1;
        $showRpmoMenu   = in_array($userRoleId, [1, 4, 5, 7]);
        $showLimitedMenus = in_array($userRoleId, [2, 3]);
    @endphp
    
    <style>
        * { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
        }
        
        :root {
            --primary-color: {{ $primaryColor }};
            --primary-rgb: {{ $primaryRgb }};
            --primary-dark-rgb: {{ $primaryDarkRgb }};
            --font-size-base: {{ $baseFontSize }};
            --bg-color: {{ $bgColor }};
            --text-color: {{ $textColor }};
            --card-bg: {{ $cardBg }};
        }
        
        body {
            font-size: var(--font-size-base);
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
        }
        
        .sidebar-transition { 
            transition: all {{ $preferences['show_animations'] ? '0.3s' : '0s' }} cubic-bezier(0.4, 0, 0.2, 1); 
        }
        
        .primary-bg {
            background-color: var(--primary-color) !important;
        }
        
        .primary-text {
            color: var(--primary-color) !important;
        }
        
        .primary-border {
            border-color: var(--primary-color) !important;
        }
        
        .primary-hover:hover {
            background-color: var(--primary-color) !important;
        }
        
        .primary-gradient {
            background: linear-gradient(135deg, 
                rgb(var(--primary-dark-rgb)), 
                rgb(var(--primary-rgb))) !important;
        }
        
        .primary-solid {
            background-color: rgb(var(--primary-rgb)) !important;
        }
        
        /* Smooth scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c62828;
            border-radius: 10px;
            border: 2px solid #f1f1f1;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #991b1b;
        }
        
        /* Navbar animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .nav-link {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Hover effects */
        .nav-link:hover {
            transform: translateX(4px);
        }
        
        .nav-link.active {
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
        }
        
        @if($preferences['compact_mode'])
        .p-6 { padding: 1rem !important; }
        .p-8 { padding: 1.5rem !important; }
        .gap-6 { gap: 1rem !important; }
        .space-y-6 > * + * { margin-top: 1rem !important; }
        @endif
        
        @if($preferences['theme'] === 'dark')
        .bg-white {
            background-color: #1f2937 !important;
        }
        .bg-gray-50 {
            background-color: #111827 !important;
        }
        .bg-gray-100 {
            background-color: #1f2937 !important;
        }
        .text-gray-900 {
            color: #f9fafb !important;
        }
        .text-gray-800 {
            color: #e5e7eb !important;
        }
        .text-gray-700 {
            color: #d1d5db !important;
        }
        .text-gray-600 {
            color: #9ca3af !important;
        }
        .text-gray-500 {
            color: #6b7280 !important;
        }
        .border-gray-200 {
            border-color: #374151 !important;
        }
        .border-gray-300 {
            border-color: #4b5563 !important;
        }
        input, select, textarea {
            background-color: #374151 !important;
            color: #f9fafb !important;
            border-color: #4b5563 !important;
        }
        input:read-only {
            background-color: #1f2937 !important;
        }
        @endif
        
        /* Card hover effect */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '{{ $primaryColor }}',
                        'primary-dark': 'rgb({{ $primaryDarkRgb }})',
                    }
                }
            }
        }
    </script>
    <style>
        #toast-container {
            position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999;
            display: flex; flex-direction: column; gap: 0.75rem;
            max-width: 22rem; width: 100%; pointer-events: none;
        }
        .toast {
            pointer-events: all; border-radius: 0.875rem;
            padding: 1rem 1rem 0 1rem; display: flex; flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden;
            transform: translateX(110%); opacity: 0;
            transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease;
        }
        .toast.show { transform: translateX(0); opacity: 1; }
        .toast.hide { transform: translateX(110%); opacity: 0; transition: transform 0.35s ease-in, opacity 0.3s ease-in; }
        .toast-body { display: flex; align-items: flex-start; gap: 0.75rem; padding-bottom: 0.875rem; }
        .toast-icon { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .toast-content { flex: 1; min-width: 0; }
        .toast-title { font-size: 0.8125rem; font-weight: 700; line-height: 1.2; }
        .toast-message { font-size: 0.8125rem; margin-top: 0.2rem; line-height: 1.4; }
        .toast-close { flex-shrink: 0; background: none; border: none; cursor: pointer; padding: 0.1rem; border-radius: 0.375rem; opacity: 0.5; transition: opacity 0.2s; line-height: 1; }
        .toast-close:hover { opacity: 1; }
        .toast-progress { height: 3px; border-radius: 0 0 0.875rem 0.875rem; margin: 0 -1rem; transform-origin: left; animation: toast-progress-shrink linear forwards; }
        @keyframes toast-progress-shrink { from { transform: scaleX(1); } to { transform: scaleX(0); } }
        .toast-success { background: #f0fdf4; border: 1.5px solid #86efac; }
        .toast-success .toast-icon { background: #dcfce7; }
        .toast-success .toast-icon svg { color: #16a34a; }
        .toast-success .toast-title { color: #14532d; }
        .toast-success .toast-message { color: #15803d; }
        .toast-success .toast-close { color: #14532d; }
        .toast-success .toast-progress { background: #22c55e; }
        .toast-error { background: #fff1f1; border: 1.5px solid #fca5a5; }
        .toast-error .toast-icon { background: #fee2e2; }
        .toast-error .toast-icon svg { color: #dc2626; }
        .toast-error .toast-title { color: #991b1b; }
        .toast-error .toast-message { color: #b91c1c; }
        .toast-error .toast-close { color: #991b1b; }
        .toast-error .toast-progress { background: #ef4444; }
        .toast-warning { background: #fffbeb; border: 1.5px solid #fcd34d; }
        .toast-warning .toast-icon { background: #fef9c3; }
        .toast-warning .toast-icon svg { color: #d97706; }
        .toast-warning .toast-title { color: #78350f; }
        .toast-warning .toast-message { color: #92400e; }
        .toast-warning .toast-close { color: #78350f; }
        .toast-warning .toast-progress { background: #f59e0b; }
        .toast-info { background: #eff6ff; border: 1.5px solid #93c5fd; }
        .toast-info .toast-icon { background: #dbeafe; }
        .toast-info .toast-icon svg { color: #2563eb; }
        .toast-info .toast-title { color: #1e3a8a; }
        .toast-info .toast-message { color: #1d4ed8; }
        .toast-info .toast-close { color: #1e3a8a; }
        .toast-info .toast-progress { background: #3b82f6; }

        /* Modal backdrop blur — applied automatically when overlay is visible */
        .modal-blur-active {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
    </style>
    @stack('styles')
</head>
<body class="text-gray-900 min-h-screen" style="background-color: var(--bg-color);">
    <div id="toast-container"></div>
    <div class="flex min-h-screen">
        
        <!-- Sidebar - Modern Design -->
        <aside id="sidebar" class="sidebar-transition fixed h-screen overflow-y-auto {{ $preferences['sidebar_style'] === 'gradient' ? 'primary-gradient' : 'primary-solid' }} text-white shadow-2xl z-50 w-64">
            <!-- Logo Section -->
            <div class="p-5 pb-2 flex items-center justify-center">
                    <div class="w-full rounded-xl p-3 backdrop-blur-sm">
                        <img src="/images/eclectic_logo_nobg.png" alt="EcoSystem Logo" class="logo-expanded w-full h-auto"/>
                        <img src="/images/logo_nobg.png" alt="EcoSystem Icon" class="logo-collapsed hidden w-20 h-auto mx-auto"/>
                    </div>
            </div>

            <!-- Navigation Menu -->
            @hasSection('sidebar-nav')
                @yield('sidebar-nav')
            @else
            <nav class="py-6 px-4">
                <!-- HOME - Visible to all roles -->
                <div class="mb-2">
                    <a href="{{ route('dashboard') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('dashboard') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-home"></i>
                        </span>
                        <span class="nav-text font-medium">Home</span>
                    </a>
                </div>
                
                <!-- CALENDAR - Visible to all roles -->
                <!-- CALENDAR Dropdown - Visible to all roles -->
                <div class="mb-2">
                    <button onclick="toggleCalendarDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('calendar*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Calendar</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="calendarChevron"></i>
                    </button>
                    <div id="calendarDropdown" class="nav-text {{ Request::is('calendar*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        <a href="{{ route('calendar.events') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('calendar/events*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-calendar-check text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Events</span>
                        </a>
                        <a href="{{ route('calendar.timesheets') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('calendar/timesheets*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-clock text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Timesheets</span>
                        </a>
                    </div>
                </div>
                
                @if($userRoleId != 3)
                <!-- REPORTING Dropdown - All internal roles except Customer (role 3) -->
                <div class="mb-2">
                    <button onclick="toggleReportingDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('reporting*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Reporting</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="reportingChevron"></i>
                    </button>
                    <div id="reportingDropdown" class="nav-text {{ Request::is('reporting*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        {{-- MD Validation: all internal roles --}}
                        <a href="{{ route('reporting') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting') && !Request::is('reporting/md-recap*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-check-circle text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">MD Validation</span>
                        </a>
                        {{-- MD Recap: admin + HoS only --}}
                        @if(in_array($userRoleId, [1, 5]))
                        <a href="{{ route('reporting.md-recap') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('reporting/md-recap*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-table text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">MD Recap</span>
                        </a>
                        @endif
                    </div>
                </div>
                
                <!-- MASTER Dropdown - Only for role_id 1 -->
                <div class="mb-2">
                    <button onclick="toggleMasterDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('master*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-database"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Master</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="masterChevron"></i>
                    </button>
                    <div id="masterDropdown" class="nav-text {{ Request::is('master*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        <a href="{{ route('master.employee.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/employee*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-users text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Employee</span>
                        </a>
                        <a href="{{ route('master.customer.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('master/customer*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-user-tie text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Customer</span>
                        </a>
                    </div>
                </div>
                
                @if($showAllMenus)
                <!-- FINANCIAL - Only for role_id 1 -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('financial') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-coins"></i>
                        </span>
                        <span class="nav-text font-medium">Financial</span>
                    </a>
                </div>

                <!-- HR & GENERAL - Only for role_id 1 -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('general') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users-cog"></i>
                        </span>
                        <span class="nav-text font-medium">HR & General</span>
                    </a>
                </div>

                <!-- BUSINESS DEV - Only for role_id 1 -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('business') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-briefcase"></i>
                        </span>
                        <span class="nav-text font-medium">Business Dev</span>
                    </a>
                </div>
                @endif
                @endif

                <!-- TICKET - Visible to all roles -->
                <div class="mb-2">
                    <a href="{{ route('ticket.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-ticket-alt"></i>
                        </span>
                        <span class="nav-text font-medium">Ticket</span>
                    </a>
                </div>

                @if(in_array($userRoleId, [2, 15]))
                <!-- MY TASKS - Employee & Employee Project (bisa jadi PIC) -->
                <div class="mb-2">
                    <a href="{{ route('ticket.task') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket/task*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-tasks"></i>
                        </span>
                        <span class="nav-text font-medium">My Tasks</span>
                    </a>
                </div>
                @endif

                @if(in_array($userRoleId, [1, 4, 5, 6, 7]))
                <!-- CONSULTANT WORKLOAD - Admin, Head, Helpdesk, RPMO -->
                <div class="mb-2">
                    <a href="{{ route('ticket.consultant-workload') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket/consultant-workload*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-users-cog"></i>
                        </span>
                        <span class="nav-text font-medium">Consultant Workload</span>
                    </a>
                </div>
                @endif

                @if(in_array($userRoleId, [1, 6, 7]))
                <!-- STAGING TICKET - Only for admin & helpdesk (not Delivery Support User) -->
                <div class="mb-2">
                    <a href="{{ route('staging.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('staging-tickets*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
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

                @if($userRoleId != 2)
                <!-- DELIVERY Dropdown - Hidden for Delivery Support User -->
                <div class="mb-2">
                    <button onclick="toggleDeliveryDropdown()" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') || Request::is('delivery/support*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-truck"></i>
                        </span>
                        <span class="nav-text flex-1 font-medium">Delivery</span>
                        <i class="fas fa-chevron-down text-xs nav-text transition-transform" id="deliveryChevron"></i>
                    </button>
                    <div id="deliveryDropdown" class="nav-text {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') || Request::is('delivery/support*') ? '' : 'hidden' }} mt-2 ml-4 space-y-1">
                        <a href="{{ route('projects.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('project*') || Request::is('planning*') || Request::is('issues*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-project-diagram text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Project</span>
                        </a>
                        <a href="{{ route('delivery.support.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('delivery/support*') ? 'bg-white bg-opacity-15 text-white font-medium' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-headset text-xs"></i>
                            </span>
                            <span class="nav-text text-sm">Support</span>
                        </a>
                    </div>
                </div>
                @endif

                @if($showAllMenus)
                <!-- ACTIVITY LOG - Only for admin -->
                <div class="mb-2">
                    <a href="{{ route('admin.activity-log') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('admin/activity-log*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-shield-alt"></i>
                        </span>
                        <span class="nav-text font-medium">Activity Log</span>
                    </a>
                </div>
                @endif

                @if($showRpmoMenu)
                <!-- RPMO - For admin, RPMO, Project Head, Support Head -->
                @php
                    $rpmoDropdownOpen = Request::is('rpmo*');
                    $showRpmoMain     = in_array($userRoleId, [1, 7]); // only admin & RPMO see the main RPMO link
                @endphp
                <div class="mb-2">
                    <button onclick="toggleRpmoDropdown()" class="nav-link w-full flex items-center gap-3 px-4 py-3 rounded-xl {{ $rpmoDropdownOpen ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all" style="background:none;border:none;cursor:pointer;text-align:left;">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-cogs"></i>
                        </span>
                        <span class="nav-text font-medium flex-1">RPMO</span>
                        <span id="rpmoChevron" class="nav-text transition-transform duration-200 {{ $rpmoDropdownOpen ? 'rotate-180' : '' }}">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </span>
                    </button>

                    <div id="rpmoSubmenu" class="{{ $rpmoDropdownOpen ? '' : 'hidden' }} pl-4 mt-1 space-y-1">
                        @if($showRpmoMain)
                        <a href="{{ route('rpmo') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo') && !Request::is('rpmo/*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-tachometer-alt"></i>
                            </span>
                            <span class="nav-text">Overview</span>
                        </a>
                        @endif
                        <a href="{{ route('rpmo.periods.index') }}" class="nav-link flex items-center gap-3 px-4 py-2 rounded-xl {{ Request::is('rpmo/periods*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-70 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all text-sm">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                            <span class="nav-text">Period Management</span>
                        </a>
                    </div>
                </div>
                @endif

                @if($showAllMenus)
                <!-- LEGAL - Only for admin -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('legal') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-balance-scale"></i>
                        </span>
                        <span class="nav-text font-medium">Legal</span>
                    </a>
                </div>
                @endif
                
                <!-- Divider -->
                <div class="my-6 border-t border-white border-opacity-10"></div>
                
                <!-- SETTINGS - Visible to all roles -->
                <div class="mb-2">
                    <a href="{{ route('settings.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('settings*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-cog"></i>
                        </span>
                        <span class="nav-text font-medium">Settings</span>
                    </a>
                </div>
            </nav>
            @endif
        </aside>

        <!-- Main Content -->
        <main id="mainContent" class="sidebar-transition flex-1 ml-64 min-w-0 overflow-x-hidden">
            <!-- Header - Modern Design -->
            <header class="sticky top-0 z-40 shadow-sm border-b border-gray-100" style="background-color: var(--card-bg);">
                <div class="px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <button onclick="toggleSidebar()" class="w-10 h-10 flex items-center justify-center border-2 rounded-xl hover:bg-opacity-10 primary-hover primary-border transition-all" style="border-color: var(--primary-color); color: var(--text-color);">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <h1 class="text-xl font-bold mb-0.5" style="color: var(--text-color);">@yield('page-title', 'Dashboard')</h1>
                            <p class="text-xs text-gray-500">@yield('page-subtitle', 'Welcome back')</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Search Bar -->
                        <!-- Notification Bell -->
                        <div class="relative" id="bellWrapper">
                            <button id="bellBtn" onclick="toggleBellDropdown()"
                                class="relative w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-red-800 hover:bg-red-50 transition-all text-gray-600 hover:text-red-800">
                                <i class="fas fa-bell"></i>
                                <span id="bellBadge" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 rounded-full border-2 border-white text-white text-[10px] font-bold flex items-center justify-center leading-none"></span>
                            </button>

                            <!-- Notification Dropdown -->
                            <div id="bellDropdown" class="hidden absolute top-full right-0 mt-2 w-80 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                    <span class="text-sm font-semibold text-gray-800">Notifications</span>
                                    <div class="flex gap-2">
                                        <button onclick="markAllNotificationsRead()" class="text-xs text-red-700 hover:underline font-medium">Mark all read</button>
                                        <a href="{{ route('notifications.index') }}" class="text-xs text-gray-500 hover:underline">View all</a>
                                    </div>
                                </div>
                                <div id="bellNotifList" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                                    <div class="px-4 py-6 text-center text-xs text-gray-400">Loading...</div>
                                </div>
                            </div>
                        </div>

                        <!-- User Menu -->
                        <div class="relative">
                            <button onclick="toggleUserDropdown()" class="flex items-center gap-3 px-4 py-2.5 border-2 border-gray-200 rounded-xl hover:bg-gray-50 hover:border-red-800 transition-all">
                                <div class="w-10 h-10 rounded-xl primary-gradient text-white flex items-center justify-center font-bold text-sm shadow-md">
                                    @if(isset($user['type']) && $user['type'] === 'customer')
                                        {{ strtoupper(substr($user['company_name'] ?? 'C', 0, 2)) }}
                                    @else
                                        {{ strtoupper(substr($user['name'] ?? 'U', 0, 2)) }}
                                    @endif
                                </div>
                                <div class="text-left hidden xl:block">
                                    <div class="text-sm font-bold text-gray-900">
                                        @if(isset($user['type']) && $user['type'] === 'customer')
                                            {{ $user['company_name'] ?? 'Customer' }}
                                        @else
                                            {{ $user['name'] ?? 'User' }}
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $user['role']['name'] ?? 'User' }}
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
                            </button>

                            <div id="userDropdown" class="hidden absolute top-full right-0 mt-2 w-64 bg-white rounded-xl shadow-2xl border-2 border-gray-100 p-2 z-50">
                                <!-- User Info -->
                                <a href="{{ route('profile.my') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
                                    <i class="fas fa-user w-5 text-center text-gray-500"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
                                    <i class="fas fa-cog w-5 text-center text-gray-500"></i>
                                    <span>Settings</span>
                                </a>
                                <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
                                    <i class="fas fa-question-circle w-5 text-center text-gray-500"></i>
                                    <span>Help & Support</span>
                                </a>
                                <hr class="my-2 border-gray-200">
                                <form action="{{ route('logout') }}" method="POST" class="w-full">
                                    @csrf
                                    <button type="submit" 
                                        class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-50 text-red-600 text-sm w-full text-left transition-all font-medium">
                                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                                        <span>Sign Out</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="@yield('content-class', 'p-6')">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        var isCalendarDropdownOpen = {{ Request::is('calendar*') ? 'true' : 'false' }};

        function toggleCalendarDropdown() {
            if (isCollapsed) return;

            var dropdown = document.getElementById('calendarDropdown');
            isCalendarDropdownOpen = !isCalendarDropdownOpen;
            dropdown.classList.toggle('hidden', !isCalendarDropdownOpen);
        }
    </script>

    <script>
        var isCollapsed = false;
        var isMasterDropdownOpen = {{ Request::is('master*') ? 'true' : 'false' }};
        var isDeliveryDropdownOpen = {{ Request::is('project*') || Request::is('support*') ? 'true' : 'false' }};
        var isReportingDropdownOpen = {{ Request::is('reporting*') ? 'true' : 'false' }};
        
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var mainContent = document.getElementById('mainContent');
            var navTexts = document.querySelectorAll('.nav-text');
            var logoExpanded = document.querySelector('.logo-expanded');
            var logoCollapsed = document.querySelector('.logo-collapsed');
            
            isCollapsed = !isCollapsed;
            
            if (isCollapsed) {
                sidebar.classList.remove('w-64');
                sidebar.classList.add('w-16');
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-16');
                navTexts.forEach(function(text) { text.classList.add('hidden'); });
                logoExpanded.classList.add('hidden');
                logoCollapsed.classList.remove('hidden');
                document.querySelectorAll('.nav-link').forEach(function(link) {
                    link.classList.add('justify-center');
                    link.classList.remove('gap-3');
                });
            } else {
                sidebar.classList.remove('w-16');
                sidebar.classList.add('w-64');
                mainContent.classList.remove('ml-16');
                mainContent.classList.add('ml-64');
                navTexts.forEach(function(text) { text.classList.remove('hidden'); });
                logoExpanded.classList.remove('hidden');
                logoCollapsed.classList.add('hidden');
                document.querySelectorAll('.nav-link').forEach(function(link) {
                    link.classList.remove('justify-center');
                    link.classList.add('gap-3');
                });
            }
        }

        function toggleMasterDropdown() {
            if (isCollapsed) return;
            isMasterDropdownOpen = !isMasterDropdownOpen;
            document.getElementById('masterDropdown').classList.toggle('hidden', !isMasterDropdownOpen);
        }

        function toggleReportingDropdown() {
            if (isCollapsed) return;
            isReportingDropdownOpen = !isReportingDropdownOpen;
            document.getElementById('reportingDropdown').classList.toggle('hidden', !isReportingDropdownOpen);
        }

        function toggleDeliveryDropdown() {
            if (isCollapsed) return;
            isDeliveryDropdownOpen = !isDeliveryDropdownOpen;
            document.getElementById('deliveryDropdown').classList.toggle('hidden', !isDeliveryDropdownOpen);
        }

        let isRpmoDropdownOpen = {{ Request::is('rpmo*') ? 'true' : 'false' }};
        function toggleRpmoDropdown() {
            if (isCollapsed) return;
            isRpmoDropdownOpen = !isRpmoDropdownOpen;
            const submenu = document.getElementById('rpmoSubmenu');
            const chevron = document.getElementById('rpmoChevron');
            if (submenu) submenu.classList.toggle('hidden', !isRpmoDropdownOpen);
            if (chevron) chevron.classList.toggle('rotate-180', isRpmoDropdownOpen);
        }

        function toggleUserDropdown() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            var userMenu = event.target.closest('button[onclick="toggleUserDropdown()"]');
            var dropdown = document.getElementById('userDropdown');
            if (!userMenu && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (!href || href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
    <script>
        const _toastIcons = {
            success: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
            error:   `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>`,
            warning: `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>`,
            info:    `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>`,
        };
        const _toastLabels = {
            success: 'Success',
            error:   'Error',
            warning: 'Warning',
            info:    'Information',
        };

        function showToast(message, type, duration = 4000) {
            type = type ?? 'info';
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-body">
                    <div class="toast-icon">${_toastIcons[type] ?? _toastIcons.info}</div>
                    <div class="toast-content">
                        <p class="toast-title">${_toastLabels[type] ?? 'Info'}</p>
                        <p class="toast-message">${message}</p>
                    </div>
                    <button class="toast-close" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
            function dismiss() {
                toast.classList.remove('show');
                toast.classList.add('hide');
                toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            }
            const timer = setTimeout(dismiss, duration);
            toast.querySelector('.toast-close').addEventListener('click', () => { clearTimeout(timer); dismiss(); });
        }

        function showNotification(message, type = 'info') {
            showToast(message, type);
        }

        function showToastClose(btn) {
            const toast = btn.closest('.toast-close');
            if (toast) toast.click();
        }

        // ── Modal backdrop blur (auto-applied, no per-modal changes needed) ──
        (function() {
            function _syncModalBlur() {
                document.querySelectorAll('.fixed.inset-0').forEach(function(el) {
                    var isOverlay = !el.classList.contains('hidden')
                        && el.id !== 'toast-container'
                        && !el.classList.contains('action-dropdown');
                    el.classList.toggle('modal-blur-active', isOverlay);
                });
            }
            new MutationObserver(_syncModalBlur).observe(document.body, {
                subtree: true, attributes: true, attributeFilter: ['class', 'style']
            });
        })();
    </script>
    @stack('scripts')

    <!-- ==================== NOTIFICATION BELL JS ==================== -->
    <script>
    (function () {
        let bellOpen = false;

        function toggleBellDropdown() {
            bellOpen = !bellOpen;
            const dropdown = document.getElementById('bellDropdown');
            if (bellOpen) {
                dropdown.classList.remove('hidden');
                loadBellNotifications();
            } else {
                dropdown.classList.add('hidden');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!document.getElementById('bellWrapper')?.contains(e.target)) {
                document.getElementById('bellDropdown')?.classList.add('hidden');
                bellOpen = false;
            }
        });

        function fetchUnreadCount() {
            fetch('/api/notifications/unread-count', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('bellBadge');
                    if (!badge) return;
                    const count = data.count || 0;
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                })
                .catch(() => {});
        }

        function loadBellNotifications() {
            const list = document.getElementById('bellNotifList');
            if (!list) return;
            fetch('/api/notifications?limit=10', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.data.length) {
                        list.innerHTML = '<div class="px-4 py-6 text-center text-xs text-gray-400">No notifications</div>';
                        return;
                    }
                    list.innerHTML = data.data.map(n => {
                        const isUnread = !n.is_read;
                        const isTimesheetSubmit = n.type === 'timesheet_submitted';
                        const notifUrl = n.ticket_id ? '/calendar/timesheets' : '/notifications';

                        let iconHtml, titleHtml;
                        if (isTimesheetSubmit) {
                            iconHtml = `<div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fas fa-file-alt text-purple-700 text-xs"></i>
                            </div>`;
                            titleHtml = `<p class="text-xs font-semibold text-gray-800 truncate">${escapeHtml(n.from_name || 'Consultant')} submitted a timesheet</p>`;
                        } else {
                            iconHtml = `<div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="fas fa-at text-red-700 text-xs"></i>
                            </div>`;
                            titleHtml = `<p class="text-xs font-semibold text-gray-800 truncate">${escapeHtml(n.from_name || 'Someone')} mentioned you</p>`;
                        }

                        return `<a href="${notifUrl}" onclick="markNotifRead(${n.id}, event)"
                            class="flex gap-3 px-4 py-3 hover:bg-gray-50 transition-colors ${isUnread ? 'bg-red-50' : ''}">
                            ${iconHtml}
                            <div class="flex-1 min-w-0">
                                ${titleHtml}
                                <p class="text-xs text-gray-500 line-clamp-2 mt-0.5">${escapeHtml(n.preview || '')}</p>
                                <p class="text-[10px] text-gray-400 mt-1">${escapeHtml(n.created_at || '')}</p>
                            </div>
                            ${isUnread ? '<span class="w-2 h-2 bg-red-500 rounded-full shrink-0 mt-2"></span>' : ''}
                        </a>`;
                    }).join('');
                })
                .catch(() => {
                    list.innerHTML = '<div class="px-4 py-6 text-center text-xs text-gray-400">Failed to load</div>';
                });
        }

        function markNotifRead(id, e) {
            fetch('/api/notifications/' + id + '/read', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
            }).catch(() => {});
            // Let the link navigate
        }

        function markAllNotificationsRead() {
            fetch('/api/notifications/read-all', {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
            }).then(() => {
                fetchUnreadCount();
                loadBellNotifications();
            }).catch(() => {});
        }

        function escapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        // Expose globally for onclick attributes
        window.toggleBellDropdown     = toggleBellDropdown;
        window.markAllNotificationsRead = markAllNotificationsRead;
        window.markNotifRead          = markNotifRead;

        // Initial count + polling every 30 s
        fetchUnreadCount();
        setInterval(fetchUnreadCount, 30000);
    })();
    </script>
</body>
</html>

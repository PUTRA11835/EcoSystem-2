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
        $showAllMenus = $userRoleId == 1;
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
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb)));
            border-radius: 10px;
            border: 2px solid #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, rgb(var(--primary-rgb)), rgb(var(--primary-dark-rgb)));
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
</head>
<body class="text-gray-900 min-h-screen" style="background-color: var(--bg-color);">
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
                
                @if($showAllMenus)
                <!-- REPORTING - Only for role_id 1 -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('reporting') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <span class="nav-text font-medium">Reporting</span>
                    </a>
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

                <!-- TICKET - Visible to all roles -->
                <div class="mb-2">
                    <a href="{{ route('ticket.index') }}" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('ticket*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-ticket-alt"></i>
                        </span>
                        <span class="nav-text font-medium">Ticket</span>
                    </a>
                </div>

                @if(in_array($userRoleId, [1, 2, 6, 7]))
                <!-- STAGING TICKET - Only for admin & helpdesk -->
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

                <!-- DELIVERY Dropdown - Visible to all roles -->
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
                
                @if($showAllMenus)
                <!-- RPMO - Only for role_id 1 -->
                <div class="mb-2">
                    <a href="#" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('rpmo') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                        <span class="nav-icon w-5 h-5 flex items-center justify-center">
                            <i class="fas fa-cogs"></i>
                        </span>
                        <span class="nav-text font-medium">RPMO</span>
                    </a>
                </div>
                
                <!-- LEGAL - Only for role_id 1 -->
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
        <main id="mainContent" class="sidebar-transition flex-1 ml-64">
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
                        <div class="relative hidden lg:block">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" placeholder="Search anything..." class="w-72 pl-11 pr-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent transition-all bg-gray-50">
                        </div>

                        <!-- Notification & Message -->
                        <div class="flex gap-2">
                            <button class="relative w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-red-800 hover:bg-red-50 transition-all text-gray-600 hover:text-red-800">
                                <i class="fas fa-bell"></i>
                                <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full border-2 border-white"></span>
                            </button>
                            <button class="relative w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-red-800 hover:bg-red-50 transition-all text-gray-600 hover:text-red-800">
                                <i class="fas fa-envelope"></i>
                                <span class="absolute top-0 right-0 w-2 h-2 bg-green-600 rounded-full border-2 border-white"></span>
                            </button>
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
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
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
            <div class="p-8">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        var isCalendarDropdownOpen = {{ Request::is('calendar*') ? 'true' : 'false' }};

        function toggleCalendarDropdown() {
            if (isCollapsed) return;

            var dropdown = document.getElementById('calendarDropdown');
            var chevron = document.getElementById('calendarChevron');

            isCalendarDropdownOpen = !isCalendarDropdownOpen;

            if (isCalendarDropdownOpen) {
                dropdown.classList.remove('hidden');
                chevron.style.transform = 'rotate(180deg)';
            } else {
                dropdown.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        }
    </script>

    <script>
        var isCollapsed = false;
        var isMasterDropdownOpen = {{ Request::is('master*') ? 'true' : 'false' }};
        var isDeliveryDropdownOpen = {{ Request::is('project*') || Request::is('support*') ? 'true' : 'false' }};
        
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var mainContent = document.getElementById('mainContent');
            var navTexts = document.querySelectorAll('.nav-text');
            var logoExpanded = document.querySelector('.logo-expanded');
            var logoCollapsed = document.querySelector('.logo-collapsed');
            
            isCollapsed = !isCollapsed;
            
            if (isCollapsed) {
                sidebar.classList.remove('w-64');
                sidebar.classList.add('w-20');
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-20');
                navTexts.forEach(function(text) { text.classList.add('hidden'); });
                logoExpanded.classList.add('hidden');
                logoCollapsed.classList.remove('hidden');
                document.querySelectorAll('.nav-link').forEach(function(link) {
                    link.classList.add('justify-center');
                    link.classList.remove('gap-3');
                });
            } else {
                sidebar.classList.remove('w-20');
                sidebar.classList.add('w-64');
                mainContent.classList.remove('ml-20');
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
            
            var dropdown = document.getElementById('masterDropdown');
            var chevron = document.getElementById('masterChevron');
            
            isMasterDropdownOpen = !isMasterDropdownOpen;
            
            if (isMasterDropdownOpen) {
                dropdown.classList.remove('hidden');
                chevron.style.transform = 'rotate(180deg)';
            } else {
                dropdown.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function toggleDeliveryDropdown() {
            if (isCollapsed) return;
            
            var dropdown = document.getElementById('deliveryDropdown');
            var chevron = document.getElementById('deliveryChevron');
            
            isDeliveryDropdownOpen = !isDeliveryDropdownOpen;
            
            if (isDeliveryDropdownOpen) {
                dropdown.classList.remove('hidden');
                chevron.style.transform = 'rotate(180deg)';
            } else {
                dropdown.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
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
    @stack('scripts')
</body>
</html>

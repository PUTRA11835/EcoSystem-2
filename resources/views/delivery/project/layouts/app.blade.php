<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title') - EC</title>
        <link rel="icon" href="{{ asset('images/logo_ec2.png') }}" type="image/png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.0.0/dist/qrcode.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen bg-gray-100 relative">
            <!-- ========== SIDEBAR ========== -->
            <aside id="sidebar" class="fixed z-40 bg-white transition-all duration-300 ease-in-out transform -translate-x-full lg:translate-x-0 h-screen w-72 lg:w-64 overflow-y-auto shadow-xl lg:shadow-none lg:border-r lg:border-gray-200">
                {{-- Logo Section --}}
                <div class="flex items-center justify-between p-6 border-b border-gray-100">
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-3">
                        <img src="{{ asset('images/logo ec.png') }}" alt="Eclectic Consulting Logo" class="h-10 w-auto">
                    </a>
                    {{-- Close button for mobile --}}
                    <button onclick="closeSidebar()" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400 lg:hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Navigation Menu --}}
                <nav class="p-4 space-y-1">
                    {{-- Dashboard --}}
                    <a href="{{ route('dashboard') }}" 
                       class="{{ request()->is('dashboard') ? 'bg-red-800 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200">
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span class="sidebar-text">Dashboard</span>
                    </a>

                    {{-- Divider --}}
                    <div class="pt-3 pb-2">
                        <hr class="border-gray-200">
                    </div>

                    {{-- Management Menu Items --}}
                    <a href="{{ route('clients.index') }}" 
                       class="{{ request()->is('clients*') ? 'bg-red-800 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200">
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h18M3 21h18" />
                        </svg>
                        <span class="sidebar-text">Manajemen Klien</span>
                    </a>
                    
                    <a href="{{ route('projects.index') }}" 
                       class="{{ request()->is('projects*') ? 'bg-red-800 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200">
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                        <span class="sidebar-text">Manajemen Delivery</span>
                    </a>
                    
                    <a href="{{ route('issues.index') }}" 
                       class="{{ request()->is('issues*') ? 'bg-red-800 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200">
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <span class="sidebar-text">Risk & Issues</span>
                    </a>

                    <a href="{{ route('planning.index') }}"
                       class="{{ request()->is('planning*') ? 'bg-red-800 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200">
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5a2.25 2.25 0 002.25-2.25m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5a2.25 2.25 0 012.25 2.25v7.5M8.25 12h.008v.008H8.25V12zm2.25 0h.008v.008h-.008V12zm2.25 0h.008v.008H10.5V12zm2.25 0h.008v.008h-.008V12zm2.25 0h.008v.008H12.75V12zm0 2.25h.008v.008H12.75v-.008zm0 2.25h.008v.008H12.75V16.5z" />
                        </svg>
                        <span class="sidebar-text">Project Planning</span>
                    </a>

                    <a href="{{ route('employees.index') }}" 
                       class="{{ request()->is('employees*') ? 'bg-red-800 text-white shadow-md' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200">
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <span class="sidebar-text">Data Karyawan</span>
                    </a>
                </nav>
            </aside>

            <!-- ========== SIDEBAR OVERLAY (MOBILE ONLY) ========== -->
            <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden lg:hidden transition-opacity duration-300"></div>

            <!-- ========== KONTEN UTAMA ========== -->
            <div id="main-content" class="flex-1 flex flex-col transition-all duration-300 ease-in-out lg:ml-64">
                <header class="bg-red-800 shadow-sm sticky top-0 z-20">
                    <div class="px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center h-16">
                            <div class="flex items-center">
                                {{-- Hamburger Menu Button --}}
                                <button onclick="toggleSidebar()" class="p-2 rounded-lg hover:bg-red-700 focus:outline-none text-white">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                    </svg>
                                </button>
                                <h1 class="ml-4 text-lg font-semibold text-white">
                                    @yield('title', 'Dashboard')
                                </h1>
                            </div>
                            <div class="flex items-center space-x-4">
                                @include('layouts.navigation-user-dropdown')
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50">
                    <!-- PERUBAHAN UTAMA: Hapus container dan gunakan max-w-full -->
                    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
                        <!-- Opsi 1: Tanpa container (full width) -->
                        @yield('content')

                    </div>
                </main>
            </div>
        </div>

        {{-- JavaScript untuk Sidebar --}}
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                const mainContent = document.getElementById('main-content');

                function toggleSidebar() {
                    if (window.innerWidth < 1024) {
                        // Mobile: toggle sidebar visibility
                        if (sidebar.classList.contains('-translate-x-full')) {
                            openSidebar();
                        } else {
                            closeSidebar();
                        }
                    } else {
                        // Desktop: toggle collapse/expand
                        sidebar.classList.toggle('lg:w-64');
                        sidebar.classList.toggle('lg:w-20');
                        mainContent.classList.toggle('lg:ml-64');
                        mainContent.classList.toggle('lg:ml-20');

                        // Toggle text visibility
                        const sidebarTexts = sidebar.querySelectorAll('.sidebar-text');
                        sidebarTexts.forEach(text => {
                            text.classList.toggle('lg:hidden');
                        });

                        // Save state
                        localStorage.setItem('sidebarCollapsed', 
                            sidebar.classList.contains('lg:w-20') ? 'true' : 'false');
                    }
                }

                function openSidebar() {
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.remove('hidden');
                    overlay.classList.add('opacity-100');
                    document.body.classList.add('overflow-hidden');
                }

                function closeSidebar() {
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.add('hidden');
                    overlay.classList.remove('opacity-100');
                    document.body.classList.remove('overflow-hidden');
                }

                // Close sidebar when clicking overlay
                overlay.addEventListener('click', closeSidebar);

                // Restore sidebar state on desktop
                if (window.innerWidth >= 1024) {
                    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (isCollapsed) {
                        sidebar.classList.remove('lg:w-64');
                        sidebar.classList.add('lg:w-20');
                        mainContent.classList.remove('lg:ml-64');
                        mainContent.classList.add('lg:ml-20');
                        const sidebarTexts = sidebar.querySelectorAll('.sidebar-text');
                        sidebarTexts.forEach(text => {
                            text.classList.add('lg:hidden');
                        });
                    }
                }

                // Handle window resize
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        if (window.innerWidth >= 1024) {
                            // Desktop view
                            sidebar.classList.remove('-translate-x-full');
                            overlay.classList.add('hidden');
                            document.body.classList.remove('overflow-hidden');
                        } else {
                            // Mobile view
                            sidebar.classList.add('-translate-x-full');
                            sidebar.classList.remove('lg:w-20');
                            sidebar.classList.add('lg:w-64');
                            mainContent.classList.remove('lg:ml-20');
                            mainContent.classList.add('lg:ml-64');
                        }
                    }, 250);
                });

                // Expose functions globally
                window.toggleSidebar = toggleSidebar;
                window.closeSidebar = closeSidebar;
            });
        </script>

        {{-- Additional Styles for Smooth Animations --}}
        <style>
            /* Smooth scrollbar for sidebar */
            #sidebar::-webkit-scrollbar {
                width: 6px;
            }
            
            #sidebar::-webkit-scrollbar-track {
                background: #f1f1f1;
            }
            
            #sidebar::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 3px;
            }
            
            #sidebar::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            /* Mobile sidebar rounded corners */
            @media (max-width: 1023px) {
                #sidebar {
                    border-top-right-radius: 1.5rem;
                    border-bottom-right-radius: 1.5rem;
                }
            }

            /* Smooth transitions */
            * {
                -webkit-tap-highlight-color: transparent;
            }
        </style>
    </body>
</html>
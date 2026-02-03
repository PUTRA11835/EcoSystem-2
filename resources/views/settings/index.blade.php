@extends('dashboard')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Settings Header -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-1">System Settings</h2>
                <p class="text-gray-600">Customize your workspace appearance and preferences</p>
            </div>
            <div class="flex gap-3">
                <button onclick="resetSettings()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all flex items-center gap-2">
                    <i class="fas fa-undo"></i>
                    Reset to Default
                </button>
                <button onclick="saveSettings()" class="px-6 py-2 primary-bg text-white rounded-lg hover:opacity-90 transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <!-- Sidebar Navigation -->
        <div class="col-span-3">
            <div class="bg-white rounded-xl shadow-sm p-4 sticky top-24">
                <nav class="space-y-1">
                    <button onclick="switchTab('appearance')" data-tab="appearance" class="settings-tab w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-all bg-red-50 text-red-800 border-l-4 border-red-800">
                        <i class="fas fa-palette w-5"></i>
                        <span class="font-medium">Appearance</span>
                    </button>
                    <button onclick="switchTab('preferences')" data-tab="preferences" class="settings-tab w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-all text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-sliders-h w-5"></i>
                        <span class="font-medium">Preferences</span>
                    </button>
                    <button onclick="switchTab('notifications')" data-tab="notifications" class="settings-tab w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-all text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-bell w-5"></i>
                        <span class="font-medium">Notifications</span>
                    </button>
                    <button onclick="switchTab('security')" data-tab="security" class="settings-tab w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-all text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-shield-alt w-5"></i>
                        <span class="font-medium">Security</span>
                    </button>
                    <button onclick="switchTab('language')" data-tab="language" class="settings-tab w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-all text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-globe w-5"></i>
                        <span class="font-medium">Language & Region</span>
                    </button>
                    <button onclick="switchTab('about')" data-tab="about" class="settings-tab w-full flex items-center gap-3 px-4 py-3 rounded-lg text-left transition-all text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-info-circle w-5"></i>
                        <span class="font-medium">About</span>
                    </button>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-span-9">
            <!-- Appearance Tab -->
            <div id="tab-appearance" class="settings-content bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Appearance Settings</h3>
                    <p class="text-gray-600">Customize the look and feel of your workspace</p>
                </div>

                <div class="p-6 space-y-8">
                    <!-- Theme Mode -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-4">Theme Mode</label>
                        <div class="grid grid-cols-3 gap-4">
                            <div onclick="selectTheme('light')" class="theme-option cursor-pointer p-6 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['theme'] === 'light' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-lg bg-gradient-to-br from-gray-100 to-white border border-gray-300 flex items-center justify-center">
                                        <i class="fas fa-sun text-2xl text-yellow-500"></i>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-semibold text-gray-900">Light</div>
                                        <div class="text-xs text-gray-500">Bright and clean</div>
                                    </div>
                                </div>
                            </div>

                            <div onclick="selectTheme('dark')" class="theme-option cursor-pointer p-6 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['theme'] === 'dark' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-lg bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center">
                                        <i class="fas fa-moon text-2xl text-blue-400"></i>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-semibold text-gray-900">Dark</div>
                                        <div class="text-xs text-gray-500">Easy on the eyes</div>
                                    </div>
                                </div>
                            </div>

                            <div onclick="selectTheme('auto')" class="theme-option cursor-pointer p-6 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['theme'] === 'auto' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-lg bg-gradient-to-r from-gray-800 to-gray-100 flex items-center justify-center">
                                        <i class="fas fa-adjust text-2xl text-gray-600"></i>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-semibold text-gray-900">Auto</div>
                                        <div class="text-xs text-gray-500">System default</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Color -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-4">Primary Color</label>
                        <div class="grid grid-cols-8 gap-3">
                            <div onclick="selectColor('#c62828')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#c62828] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#c62828' ? 'ring-4 ring-offset-2 ring-red-800' : '' }}">
                                    @if($preferences['primary_color'] === '#c62828')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#1976d2')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#1976d2] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#1976d2' ? 'ring-4 ring-offset-2 ring-blue-700' : '' }}">
                                    @if($preferences['primary_color'] === '#1976d2')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#388e3c')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#388e3c] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#388e3c' ? 'ring-4 ring-offset-2 ring-green-700' : '' }}">
                                    @if($preferences['primary_color'] === '#388e3c')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#f57c00')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#f57c00] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#f57c00' ? 'ring-4 ring-offset-2 ring-orange-700' : '' }}">
                                    @if($preferences['primary_color'] === '#f57c00')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#7b1fa2')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#7b1fa2] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#7b1fa2' ? 'ring-4 ring-offset-2 ring-purple-700' : '' }}">
                                    @if($preferences['primary_color'] === '#7b1fa2')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#0097a7')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#0097a7] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#0097a7' ? 'ring-4 ring-offset-2 ring-cyan-700' : '' }}">
                                    @if($preferences['primary_color'] === '#0097a7')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#5d4037')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#5d4037] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#5d4037' ? 'ring-4 ring-offset-2 ring-brown-700' : '' }}">
                                    @if($preferences['primary_color'] === '#5d4037')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                            <div onclick="selectColor('#455a64')" class="color-option cursor-pointer">
                                <div class="w-12 h-12 rounded-lg bg-[#455a64] flex items-center justify-center hover:scale-110 transition-transform {{ $preferences['primary_color'] === '#455a64' ? 'ring-4 ring-offset-2 ring-gray-700' : '' }}">
                                    @if($preferences['primary_color'] === '#455a64')
                                    <i class="fas fa-check text-white"></i>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Style -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-4">Sidebar Style</label>
                        <div class="grid grid-cols-2 gap-4">
                            <div onclick="selectSidebarStyle('gradient')" class="cursor-pointer p-4 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['sidebar_style'] === 'gradient' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-lg bg-gradient-to-br from-red-800 to-red-950"></div>
                                    <div>
                                        <div class="font-semibold text-gray-900">Gradient</div>
                                        <div class="text-xs text-gray-500">Modern gradient background</div>
                                    </div>
                                </div>
                            </div>
                            <div onclick="selectSidebarStyle('solid')" class="cursor-pointer p-4 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['sidebar_style'] === 'solid' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-lg bg-red-800"></div>
                                    <div>
                                        <div class="font-semibold text-gray-900">Solid</div>
                                        <div class="text-xs text-gray-500">Clean solid color</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Font Size -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-4">Font Size</label>
                        <div class="grid grid-cols-3 gap-4">
                            <div onclick="selectFontSize('small')" class="cursor-pointer p-4 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['font_size'] === 'small' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="text-center">
                                    <div class="text-sm mb-2">Aa</div>
                                    <div class="font-semibold text-gray-900">Small</div>
                                </div>
                            </div>
                            <div onclick="selectFontSize('medium')" class="cursor-pointer p-4 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['font_size'] === 'medium' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="text-center">
                                    <div class="text-base mb-2">Aa</div>
                                    <div class="font-semibold text-gray-900">Medium</div>
                                </div>
                            </div>
                            <div onclick="selectFontSize('large')" class="cursor-pointer p-4 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['font_size'] === 'large' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="text-center">
                                    <div class="text-lg mb-2">Aa</div>
                                    <div class="font-semibold text-gray-900">Large</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div class="space-y-4 pt-4 border-t">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-semibold text-gray-900">Compact Mode</div>
                                <div class="text-sm text-gray-600">Reduce spacing and padding</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="compactMode" class="sr-only peer" {{ $preferences['compact_mode'] ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-semibold text-gray-900">Show Animations</div>
                                <div class="text-sm text-gray-600">Enable smooth transitions and animations</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="showAnimations" class="sr-only peer" {{ $preferences['show_animations'] ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div id="tab-preferences" class="settings-content bg-white rounded-xl shadow-sm hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">General Preferences</h3>
                    <p class="text-gray-600">Manage your general application preferences</p>
                </div>

                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-eye text-red-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Auto-save</div>
                                    <div class="text-sm text-gray-600">Automatically save your work</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="autoSave" class="sr-only peer" checked>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-sync text-blue-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Auto-refresh</div>
                                    <div class="text-sm text-gray-600">Refresh data automatically</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="autoRefresh" class="sr-only peer">
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-keyboard text-green-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Keyboard Shortcuts</div>
                                    <div class="text-sm text-gray-600">Enable keyboard shortcuts</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="keyboardShortcuts" class="sr-only peer" checked>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div id="tab-notifications" class="settings-content bg-white rounded-xl shadow-sm hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Notification Settings</h3>
                    <p class="text-gray-600">Control how you receive notifications</p>
                </div>

                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-red-50 to-white border border-red-200 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-red-800 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-bell text-white"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Enable Notifications</div>
                                    <div class="text-sm text-gray-600">Receive all types of notifications</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="notificationsEnabled" class="sr-only peer" {{ $preferences['notifications_enabled'] ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-envelope text-purple-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Email Notifications</div>
                                    <div class="text-sm text-gray-600">Get notifications via email</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="emailNotifications" class="sr-only peer" {{ $preferences['email_notifications'] ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-mobile-alt text-blue-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Push Notifications</div>
                                    <div class="text-sm text-gray-600">Receive push notifications on your device</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="pushNotifications" class="sr-only peer" {{ $preferences['push_notifications'] ? 'checked' : '' }}>
                                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div id="tab-security" class="settings-content bg-white rounded-xl shadow-sm hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Security Settings</h3>
                    <p class="text-gray-600">Manage your account security and privacy</p>
                </div>

                <div class="p-6 space-y-6">
                    <div class="space-y-4">
                        <div class="p-6 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-lock text-green-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Change Password</div>
                                    <div class="text-sm text-gray-600">Update your account password</div>
                                </div>
                            </div>
                            <button class="px-4 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition-all">
                                Change Password
                            </button>
                        </div>

                        <div class="p-6 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-user-shield text-blue-800"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">Two-Factor Authentication</div>
                                        <div class="text-sm text-gray-600">Add an extra layer of security</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="twoFactorAuth" class="sr-only peer">
                                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-800"></div>
                                </label>
                            </div>
                        </div>

                        <div class="p-6 border border-gray-200 rounded-lg">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-history text-orange-800"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Active Sessions</div>
                                    <div class="text-sm text-gray-600">View and manage your active sessions</div>
                                </div>
                            </div>
                            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                                Manage Sessions
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Language & Region Tab -->
            <div id="tab-language" class="settings-content bg-white rounded-xl shadow-sm hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Language & Region</h3>
                    <p class="text-gray-600">Set your language and regional preferences</p>
                </div>

                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">Language</label>
                        <select id="language" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800">
                            <option value="en" {{ $preferences['language'] === 'en' ? 'selected' : '' }}>English</option>
                            <option value="id" {{ $preferences['language'] === 'id' ? 'selected' : '' }}>Bahasa Indonesia</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">Timezone</label>
                        <select id="timezone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800">
                            <option value="Asia/Jakarta" {{ $preferences['timezone'] === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (WIB)</option>
                            <option value="Asia/Singapore" {{ $preferences['timezone'] === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT)</option>
                            <option value="UTC" {{ $preferences['timezone'] === 'UTC' ? 'selected' : '' }}>UTC</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-3">Date Format</label>
                        <select id="dateFormat" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800">
                            <option value="DD/MM/YYYY" {{ $preferences['date_format'] === 'DD/MM/YYYY' ? 'selected' : '' }}>DD/MM/YYYY</option>
                            <option value="MM/DD/YYYY" {{ $preferences['date_format'] === 'MM/DD/YYYY' ? 'selected' : '' }}>MM/DD/YYYY</option>
                            <option value="YYYY-MM-DD" {{ $preferences['date_format'] === 'YYYY-MM-DD' ? 'selected' : '' }}>YYYY-MM-DD</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- About Tab -->
            <div id="tab-about" class="settings-content bg-white rounded-xl shadow-sm hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">About ECoSystem</h3>
                    <p class="text-gray-600">System information and version details</p>
                </div>

                <div class="p-6">
                    <div class="text-center py-8">
                        <div class="w-24 h-24 bg-gradient-to-br from-red-800 to-red-950 rounded-2xl mx-auto mb-6 flex items-center justify-center">
                            <i class="fas fa-leaf text-white text-4xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">ECoSystem</h2>
                        <p class="text-gray-600 mb-6">Enterprise Management Platform</p>
                        
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-green-100 text-green-800 rounded-full text-sm font-semibold mb-8">
                            <i class="fas fa-check-circle"></i>
                            Version 1.0.0
                        </div>

                        <div class="max-w-md mx-auto space-y-3">
                            <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-600">Release Date</span>
                                <span class="font-semibold text-gray-900">November 2025</span>
                            </div>
                            <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-600">License</span>
                                <span class="font-semibold text-gray-900">Enterprise</span>
                            </div>
                            <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-gray-600">Last Updated</span>
                                <span class="font-semibold text-gray-900">{{ now()->format('d M Y') }}</span>
                            </div>
                        </div>

                        <div class="mt-8 pt-8 border-t">
                            <p class="text-sm text-gray-600">
                                © {{ now()->year }} Putra & Natanael. All rights reserved.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    let currentSettings = @json($preferences);

    // Tab Switching
    function switchTab(tabName) {
        document.querySelectorAll('.settings-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        document.querySelectorAll('.settings-tab').forEach(tab => {
            tab.classList.remove('bg-red-50', 'text-red-800', 'border-l-4', 'border-red-800');
            tab.classList.add('text-gray-700');
        });

        document.getElementById(`tab-${tabName}`).classList.remove('hidden');
        
        const activeTab = document.querySelector(`[data-tab="${tabName}"]`);
        activeTab.classList.add('bg-red-50', 'text-red-800', 'border-l-4', 'border-red-800');
        activeTab.classList.remove('text-gray-700');
    }

    // Theme Selection
    function selectTheme(theme) {
        currentSettings.theme = theme;
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.remove('border-red-800', 'bg-red-50');
            option.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
        applyThemePreview(theme);
    }

    // Color Selection
    function selectColor(color) {
        currentSettings.primary_color = color;
        document.querySelectorAll('.color-option div div').forEach(div => {
            div.classList.remove('ring-4', 'ring-offset-2');
            div.innerHTML = '';
        });
        const selectedDiv = event.currentTarget.querySelector('div');
        selectedDiv.classList.add('ring-4', 'ring-offset-2');
        selectedDiv.innerHTML = '<i class="fas fa-check text-white"></i>';
        applyColorPreview(color);
    }

    // Sidebar Style Selection
    function selectSidebarStyle(style) {
        currentSettings.sidebar_style = style;
        document.querySelectorAll('[onclick^="selectSidebarStyle"]').forEach(option => {
            option.classList.remove('border-red-800', 'bg-red-50');
            option.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
    }

    // Font Size Selection
    function selectFontSize(size) {
        currentSettings.font_size = size;
        document.querySelectorAll('[onclick^="selectFontSize"]').forEach(option => {
            option.classList.remove('border-red-800', 'bg-red-50');
            option.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
        applyFontSizePreview(size);
    }

    function applyColorPreview(color) {
        document.documentElement.style.setProperty('--primary-color', color);
        
        // Convert hex to RGB
        const rgb = hexToRgb(color);
        if (rgb) {
            document.documentElement.style.setProperty('--primary-rgb', `${rgb.r}, ${rgb.g}, ${rgb.b}`);
            
            // Calculate darker shade
            const darkR = Math.max(0, rgb.r - 40);
            const darkG = Math.max(0, rgb.g - 40);
            const darkB = Math.max(0, rgb.b - 40);
            document.documentElement.style.setProperty('--primary-dark-rgb', `${darkR}, ${darkG}, ${darkB}`);
        }
        
        // Update all primary colored elements
        updatePrimaryElements(color);
    }

    // Sidebar Style dengan update real-time
    function selectSidebarStyle(style) {
        currentSettings.sidebar_style = style;
        document.querySelectorAll('[onclick^="selectSidebarStyle"]').forEach(option => {
            option.classList.remove('border-red-800', 'bg-red-50');
            option.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
        
        // Apply sidebar style immediately
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            if (style === 'gradient') {
                sidebar.classList.remove('primary-solid');
                sidebar.classList.add('primary-gradient');
            } else {
                sidebar.classList.remove('primary-gradient');
                sidebar.classList.add('primary-solid');
            }
        }
    }

    // Helper function to convert hex to RGB
    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    // Update all primary colored elements
    function updatePrimaryElements(color) {
        // Update buttons
        document.querySelectorAll('.primary-bg, .bg-red-800, .hover\\:bg-red-900').forEach(el => {
            el.style.backgroundColor = color;
        });
        
        // Update text colors
        document.querySelectorAll('.text-red-800, .primary-text').forEach(el => {
            el.style.color = color;
        });
        
        // Update borders
        document.querySelectorAll('.border-red-800, .primary-border').forEach(el => {
            el.style.borderColor = color;
        });
    }

    // Apply Theme Preview
    function applyThemePreview(theme) {
        if (theme === 'dark') {
            document.body.classList.add('bg-gray-900');
            document.body.classList.remove('bg-gray-50');
        } else {
            document.body.classList.add('bg-gray-50');
            document.body.classList.remove('bg-gray-900');
        }
    }

    // Apply Color Preview
    function applyColorPreview(color) {
        // Preview color change (simplified)
        console.log('Color preview:', color);
    }

    // Apply Font Size Preview
    function applyFontSizePreview(size) {
        const sizes = {
            small: '14px',
            medium: '16px',
            large: '18px'
        };
        document.documentElement.style.fontSize = sizes[size];
    }

    // Save Settings
    async function saveSettings() {
        // Collect all toggle values
        currentSettings.compact_mode = document.getElementById('compactMode').checked;
        currentSettings.show_animations = document.getElementById('showAnimations').checked;
        currentSettings.notifications_enabled = document.getElementById('notificationsEnabled').checked;
        currentSettings.email_notifications = document.getElementById('emailNotifications').checked;
        currentSettings.push_notifications = document.getElementById('pushNotifications').checked;
        currentSettings.language = document.getElementById('language').value;
        currentSettings.timezone = document.getElementById('timezone').value;
        currentSettings.date_format = document.getElementById('dateFormat').value;

        try {
            const response = await fetch('/settings/preferences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(currentSettings)
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Settings saved successfully!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification('Failed to save settings', 'error');
            }
        } catch (error) {
            console.error('Save settings error:', error);
            showNotification('An error occurred while saving', 'error');
        }
    }

    // Reset Settings
    async function resetSettings() {
        if (!confirm('Are you sure you want to reset all settings to default?')) {
            return;
        }

        try {
            const response = await fetch('/settings/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Settings reset successfully!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showNotification('Failed to reset settings', 'error');
            }
        } catch (error) {
            console.error('Reset settings error:', error);
            showNotification('An error occurred while resetting', 'error');
        }
    }

    // Show Notification
    function showNotification(message, type = 'info') {
        const bgColor = type === 'success' ? 'bg-green-500' : 
                        type === 'error' ? 'bg-red-500' : 
                        type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500';
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Expose functions to window
    window.switchTab = switchTab;
    window.selectTheme = selectTheme;
    window.selectColor = selectColor;
    window.selectSidebarStyle = selectSidebarStyle;
    window.selectFontSize = selectFontSize;
    window.saveSettings = saveSettings;
    window.resetSettings = resetSettings;
})();
</script>
@endsection
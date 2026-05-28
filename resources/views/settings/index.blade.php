@extends('dashboard')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage your workspace preferences and account security</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="resetSettings()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Reset to Default
            </button>
            <button onclick="saveSettings()"
                class="inline-flex items-center gap-2 px-5 py-2 bg-red-800 text-white text-sm font-medium rounded-lg hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                </svg>
                Save Changes
            </button>
        </div>
    </div>

    {{-- Tabs + Content --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">

        {{-- Tab Navigation --}}
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px overflow-x-auto">
                <button onclick="switchTab('appearance')" data-tab="appearance"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-red-800 text-red-800 whitespace-nowrap flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                    </svg>
                    Appearance
                </button>
                <button onclick="switchTab('preferences')" data-tab="preferences"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-red-800 hover:border-gray-300 whitespace-nowrap flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    Preferences
                </button>
                <button onclick="switchTab('notifications')" data-tab="notifications"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-red-800 hover:border-gray-300 whitespace-nowrap flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    Notifications
                </button>
                <button onclick="switchTab('security')" data-tab="security"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-red-800 hover:border-gray-300 whitespace-nowrap flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                    Security
                </button>
                <button onclick="switchTab('language')" data-tab="language"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-red-800 hover:border-gray-300 whitespace-nowrap flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />
                    </svg>
                    Language & Region
                </button>
                <button onclick="switchTab('about')" data-tab="about"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-red-800 hover:border-gray-300 whitespace-nowrap flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    About
                </button>
            </nav>
        </div>

        {{-- ── APPEARANCE TAB ── --}}
        <div id="tab-appearance" class="settings-content p-6">
            <div class="space-y-5">

                {{-- Theme Mode --}}
                <div class="border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 bg-yellow-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Theme Mode</div>
                            <div class="text-xs text-gray-400">Choose how the interface looks</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" onclick="selectTheme('light')"
                            class="theme-option group flex flex-col items-center gap-2 p-3 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['theme'] === 'light' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                            <div class="w-full h-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold {{ $preferences['theme'] === 'light' ? 'text-red-800' : 'text-gray-600' }}">Light</span>
                        </button>
                        <button type="button" onclick="selectTheme('dark')"
                            class="theme-option group flex flex-col items-center gap-2 p-3 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['theme'] === 'dark' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                            <div class="w-full h-9 rounded-lg bg-gray-900 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold {{ $preferences['theme'] === 'dark' ? 'text-red-800' : 'text-gray-600' }}">Dark</span>
                        </button>
                        <button type="button" onclick="selectTheme('auto')"
                            class="theme-option group flex flex-col items-center gap-2 p-3 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['theme'] === 'auto' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                            <div class="w-full h-9 rounded-lg bg-gradient-to-r from-gray-900 to-white flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                                </svg>
                            </div>
                            <span class="text-xs font-semibold {{ $preferences['theme'] === 'auto' ? 'text-red-800' : 'text-gray-600' }}">System</span>
                        </button>
                    </div>
                </div>

                {{-- Accent Color --}}
                <div class="border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: {{ ($preferences['primary_color'] ?? '#c62828') }}22">
                            <div class="w-4 h-4 rounded-full" style="background-color: {{ $preferences['primary_color'] ?? '#c62828' }}"></div>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-800">Accent Color</div>
                            <div class="text-xs text-gray-400">Buttons, active states &amp; highlights</div>
                        </div>
                    </div>
                    @php
                        $colors = [
                            '#c62828' => ['Red',    'ring-red-700'],
                            '#1976d2' => ['Blue',   'ring-blue-600'],
                            '#388e3c' => ['Green',  'ring-green-600'],
                            '#f57c00' => ['Orange', 'ring-orange-600'],
                            '#7b1fa2' => ['Purple', 'ring-purple-700'],
                            '#0097a7' => ['Teal',   'ring-cyan-600'],
                            '#5d4037' => ['Brown',  'ring-yellow-900'],
                            '#455a64' => ['Slate',  'ring-gray-600'],
                        ];
                    @endphp
                    <div class="flex flex-wrap gap-3">
                        @foreach($colors as $hex => [$label, $ring])
                        <button type="button" onclick="selectColor('{{ $hex }}')" title="{{ $label }}"
                            class="color-option group flex flex-col items-center gap-1.5">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center transition-transform hover:scale-110 {{ $preferences['primary_color'] === $hex ? 'ring-3 ring-offset-2 '.$ring : '' }}"
                                style="background-color: {{ $hex }}">
                                @if($preferences['primary_color'] === $hex)
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400 leading-none">{{ $label }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Sidebar + Font Size --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Sidebar Style --}}
                    <div class="border border-gray-200 rounded-xl p-5">
                        <div class="text-sm font-semibold text-gray-800 mb-0.5">Sidebar Style</div>
                        <div class="text-xs text-gray-400 mb-3">Background of the left navigation</div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" onclick="selectSidebarStyle('gradient')"
                                class="flex flex-col items-center gap-1.5 p-2 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['sidebar_style'] === 'gradient' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="w-full h-8 rounded-md bg-gradient-to-br from-red-800 to-red-950"></div>
                                <span class="text-xs font-medium {{ $preferences['sidebar_style'] === 'gradient' ? 'text-red-800' : 'text-gray-600' }}">Gradient</span>
                            </button>
                            <button type="button" onclick="selectSidebarStyle('solid')"
                                class="flex flex-col items-center gap-1.5 p-2 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['sidebar_style'] === 'solid' ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <div class="w-full h-8 rounded-md bg-red-800"></div>
                                <span class="text-xs font-medium {{ $preferences['sidebar_style'] === 'solid' ? 'text-red-800' : 'text-gray-600' }}">Solid</span>
                            </button>
                        </div>
                    </div>

                    {{-- Font Size --}}
                    <div class="border border-gray-200 rounded-xl p-5">
                        <div class="text-sm font-semibold text-gray-800 mb-0.5">Font Size</div>
                        <div class="text-xs text-gray-400 mb-3">Text size across the interface</div>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['small' => ['Aa','Small','text-sm'], 'medium' => ['Aa','Medium','text-base'], 'large' => ['Aa','Large','text-lg']] as $size => [$sample, $label, $class])
                            <button type="button" onclick="selectFontSize('{{ $size }}')"
                                class="flex flex-col items-center gap-1 p-2.5 border-2 rounded-xl transition-all hover:border-red-800 {{ $preferences['font_size'] === $size ? 'border-red-800 bg-red-50' : 'border-gray-200' }}">
                                <span class="{{ $class }} font-bold {{ $preferences['font_size'] === $size ? 'text-red-800' : 'text-gray-700' }}">{{ $sample }}</span>
                                <span class="text-xs {{ $preferences['font_size'] === $size ? 'text-red-700' : 'text-gray-400' }}">{{ $label }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>

                </div>

                {{-- Display Options --}}
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Display Options</div>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div class="flex items-center justify-between px-5 py-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Compact Mode</div>
                                <div class="text-xs text-gray-400 mt-0.5">Reduce spacing and padding throughout the UI</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                                <input type="checkbox" id="compactMode" class="sr-only peer" {{ $preferences['compact_mode'] ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between px-5 py-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Show Animations</div>
                                <div class="text-xs text-gray-400 mt-0.5">Enable smooth transitions and motion effects</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                                <input type="checkbox" id="showAnimations" class="sr-only peer" {{ $preferences['show_animations'] ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── PREFERENCES TAB ── --}}
        <div id="tab-preferences" class="settings-content p-6 hidden">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">General Preferences</h3>
            <p class="text-xs text-gray-400 mb-6">Manage how the application behaves</p>

            <div class="space-y-3 max-w-lg">
                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-800" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Auto-save</div>
                            <div class="text-xs text-gray-400">Automatically save your work in progress</div>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                        <input type="checkbox" id="autoSave" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Auto-refresh</div>
                            <div class="text-xs text-gray-400">Refresh data automatically in the background</div>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                        <input type="checkbox" id="autoRefresh" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Keyboard Shortcuts</div>
                            <div class="text-xs text-gray-400">Enable global keyboard shortcut bindings</div>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                        <input type="checkbox" id="keyboardShortcuts" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                    </label>
                </div>
            </div>
        </div>

        {{-- ── NOTIFICATIONS TAB ── --}}
        <div id="tab-notifications" class="settings-content p-6 hidden">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">Notification Settings</h3>
            <p class="text-xs text-gray-400 mb-6">Control how and when you receive notifications</p>

            <div class="space-y-3 max-w-lg">
                <div class="flex items-center justify-between p-4 border border-red-200 bg-red-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-800 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Enable Notifications</div>
                            <div class="text-xs text-gray-500">Master switch for all notification types</div>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                        <input type="checkbox" id="notificationsEnabled" class="sr-only peer" {{ $preferences['notifications_enabled'] ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-purple-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Email Notifications</div>
                            <div class="text-xs text-gray-400">Receive notifications via email</div>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                        <input type="checkbox" id="emailNotifications" class="sr-only peer" {{ $preferences['email_notifications'] ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Push Notifications</div>
                            <div class="text-xs text-gray-400">Receive push notifications on your device</div>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                        <input type="checkbox" id="pushNotifications" class="sr-only peer" {{ $preferences['push_notifications'] ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                    </label>
                </div>
            </div>

            {{-- Notification Sound Picker --}}
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">Notification Sound</h3>
                <p class="text-xs text-gray-400 mb-4">Choose a different sound for each notification type</p>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                    {{-- New Ticket / Alert --}}
                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-7 h-7 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-bell text-red-700 text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-gray-700">New Ticket / Alert</div>
                                <div class="text-xs text-gray-400">Badge notification &amp; new ticket</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="se-wrap flex-1" id="soundWrapTicket">
                                <button type="button" class="se-btn" onclick="_soundDDToggle('soundWrapTicket')">
                                    <span class="se-label is-placeholder" id="soundLabelTicket">Loading sounds…</span>
                                    <svg class="se-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="sound-dd-panel" id="soundPanelTicket" role="listbox"></div>
                            </div>
                            <button type="button"
                                onclick="previewSound(document.getElementById('soundWrapTicket').dataset.value)"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-all flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>
                                Preview
                            </button>
                        </div>
                    </div>

                    {{-- Chat / Message --}}
                    <div class="border border-gray-200 rounded-xl p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-7 h-7 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-comment-dots text-blue-700 text-xs"></i>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-gray-700">Chat / Message</div>
                                <div class="text-xs text-gray-400">Incoming message or reply on ticket</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="se-wrap flex-1" id="soundWrapChat">
                                <button type="button" class="se-btn" onclick="_soundDDToggle('soundWrapChat')">
                                    <span class="se-label is-placeholder" id="soundLabelChat">Loading sounds…</span>
                                    <svg class="se-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div class="sound-dd-panel" id="soundPanelChat" role="listbox"></div>
                            </div>
                            <button type="button"
                                onclick="previewSound(document.getElementById('soundWrapChat').dataset.value)"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-all flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z"/></svg>
                                Preview
                            </button>
                        </div>
                    </div>

                </div>

                <p class="text-xs text-gray-400 mt-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 inline mr-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    Settings are saved locally in your browser. Sound must be enabled (🔊 button) for it to play.
                </p>
            </div>
        </div>

        {{-- ── SECURITY TAB ── --}}
        <div id="tab-security" class="settings-content p-6 hidden">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">Security Settings</h3>
            <p class="text-xs text-gray-400 mb-6">Manage your account security and access</p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Change Password --}}
                <div class="border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900">Change Password</div>
                            <div class="text-xs text-gray-400">Update your account password</div>
                        </div>
                    </div>
                    <form id="changePasswordForm" onsubmit="changePassword(event)" class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">New Password</label>
                            <div class="relative">
                                <input type="password" id="newPassword" name="password"
                                    class="w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                                    placeholder="Minimum 8 characters">
                                <button type="button" onclick="togglePassword('newPassword', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 eye-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" name="password_confirmation"
                                    class="w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                                    placeholder="Re-enter new password">
                                <button type="button" onclick="togglePassword('confirmPassword', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 eye-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </div>
                            <p id="confirmPasswordError" class="text-red-600 text-xs mt-1 hidden"></p>
                        </div>
                        <button type="submit" id="changePasswordBtn"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-medium rounded-lg hover:bg-red-900 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z" />
                            </svg>
                            Update Password
                        </button>
                    </form>
                </div>

                {{-- Right column --}}
                <div class="space-y-4">
                    {{-- 2FA --}}
                    <div class="border border-gray-200 rounded-xl p-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Two-Factor Authentication</div>
                                    <div class="text-xs text-gray-400">Add an extra layer of security</div>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-4">
                                <input type="checkbox" id="twoFactorAuth" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-800"></div>
                            </label>
                        </div>
                    </div>

                    {{-- Active Sessions --}}
                    <div class="border border-gray-200 rounded-xl p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-orange-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Active Sessions</div>
                                <div class="text-xs text-gray-400">View and manage your active sessions</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg mb-3">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-gray-900 truncate">Current Session</div>
                                <div class="text-xs text-gray-400">{{ request()->ip() }} · Active now</div>
                            </div>
                            <span class="text-xs text-green-700 font-medium bg-green-100 px-2 py-0.5 rounded-full">Active</span>
                        </div>
                        <button class="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                            Manage Sessions
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── LANGUAGE & REGION TAB ── --}}
        <div id="tab-language" class="settings-content p-6 hidden">
            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">Language & Region</h3>
            <p class="text-xs text-gray-400 mb-6">Set your language and regional preferences</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-w-xl">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Language</label>
                    <select id="language" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="en" {{ $preferences['language'] === 'en' ? 'selected' : '' }}>English</option>
                        <option value="id" {{ $preferences['language'] === 'id' ? 'selected' : '' }}>Bahasa Indonesia</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Timezone</label>
                    <select id="timezone" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="Asia/Jakarta" {{ $preferences['timezone'] === 'Asia/Jakarta' ? 'selected' : '' }}>Asia/Jakarta (WIB)</option>
                        <option value="Asia/Singapore" {{ $preferences['timezone'] === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT)</option>
                        <option value="UTC" {{ $preferences['timezone'] === 'UTC' ? 'selected' : '' }}>UTC</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Date Format</label>
                    <select id="dateFormat" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 bg-white">
                        <option value="DD/MM/YYYY" {{ $preferences['date_format'] === 'DD/MM/YYYY' ? 'selected' : '' }}>DD/MM/YYYY</option>
                        <option value="MM/DD/YYYY" {{ $preferences['date_format'] === 'MM/DD/YYYY' ? 'selected' : '' }}>MM/DD/YYYY</option>
                        <option value="YYYY-MM-DD" {{ $preferences['date_format'] === 'YYYY-MM-DD' ? 'selected' : '' }}>YYYY-MM-DD</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ── ABOUT TAB ── --}}
        <div id="tab-about" class="settings-content hidden overflow-hidden">

            {{-- Hero Banner --}}
            <div class="bg-gradient-to-br from-red-800 via-red-900 to-red-950 px-8 py-10">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center flex-shrink-0 border border-white/20 shadow-lg overflow-hidden">
                        <img src="/images/logo_nobg.png" alt="ECoSystem" class="w-12 h-12 object-contain">
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl font-bold text-white tracking-tight">ECoSystem</h1>
                        <p class="text-red-200 text-sm mt-0.5">Enterprise Management Platform</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white/15 text-white rounded-full text-xs font-semibold border border-white/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                Version 1.0.0
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-medium border border-emerald-400/25">
                                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
                                Up to date
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="p-6 space-y-5">

                {{-- Info Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Release Date</div>
                            <div class="text-sm font-semibold text-gray-900">November 2025</div>
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">Last Updated</div>
                            <div class="text-sm font-semibold text-gray-900">{{ now()->format('d M Y') }}</div>
                        </div>
                    </div>
                    <div class="border border-gray-200 rounded-xl p-4 flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-purple-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-400">License</div>
                            <div class="text-sm font-semibold text-gray-900">Enterprise</div>
                        </div>
                    </div>
                </div>

                {{-- Platform Modules --}}
                <div class="border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-red-800" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800">Platform Modules</h3>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2.5">
                        @foreach([
                            ['icon' => 'fa-ticket-alt',   'label' => 'Ticket Management'],
                            ['icon' => 'fa-headset',      'label' => 'Delivery Support'],
                            ['icon' => 'fa-project-diagram','label' => 'Delivery Project'],
                            ['icon' => 'fa-clock',        'label' => 'Timesheet'],
                            ['icon' => 'fa-chart-bar',    'label' => 'Reporting'],
                            ['icon' => 'fa-users',        'label' => 'Employee Management'],
                        ] as $mod)
                        <div class="flex items-center gap-2.5 text-xs text-gray-600">
                            <div class="w-6 h-6 bg-red-50 rounded-md flex items-center justify-center flex-shrink-0">
                                <i class="fas {{ $mod['icon'] }} text-red-800 text-xs"></i>
                            </div>
                            {{ $mod['label'] }}
                        </div>
                        @endforeach
                    </div>
                </div>

{{-- Team & Copyright --}}
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-1 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Developed by</span>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold border border-gray-200">
                                <span class="w-4 h-4 bg-red-800 text-white rounded-full flex items-center justify-center text-xs font-bold leading-none">P</span>
                                Putra
                            </span>
                            <span class="text-gray-300">&amp;</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-semibold border border-gray-200">
                                <span class="w-4 h-4 bg-red-800 text-white rounded-full flex items-center justify-center text-xs font-bold leading-none">N</span>
                                Natanael
                            </span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">© {{ now()->year }} Putra &amp; Natanael. All rights reserved.</p>
                </div>

            </div>
        </div>

    </div>{{-- end card --}}
</div>

<style>
.sound-dd-panel {
    display: none;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.18);
    max-height: 340px;
    overflow-y: auto;
    padding: 0.375rem 0;
}
.sound-dd-panel.sound-dd-open {
    display: block;
    position: fixed;
    z-index: 9999;
}
.sound-dd-item {
    display: block;
    width: 100%;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    color: #4b5563;
    text-align: left;
    background: transparent;
    border: 0;
    cursor: pointer;
}
.sound-dd-item:hover { background: #f9fafb; }
.sound-dd-item.is-active { background: #f9fafb; color: #111827; font-weight: 500; }
</style>

<script>
(function () {
    'use strict';

    let currentSettings = @json($preferences);

    // ── Tab Switching ──────────────────────────────────────────────
    function switchTab(tabName) {
        document.querySelectorAll('.settings-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.settings-tab').forEach(btn => {
            btn.classList.remove('border-red-800', 'text-red-800');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        document.getElementById('tab-' + tabName).classList.remove('hidden');
        const active = document.querySelector('[data-tab="' + tabName + '"]');
        active.classList.add('border-red-800', 'text-red-800');
        active.classList.remove('border-transparent', 'text-gray-500');
    }

    // ── Appearance Selectors ───────────────────────────────────────
    function selectTheme(theme) {
        currentSettings.theme = theme;
        document.querySelectorAll('.theme-option').forEach(o => {
            o.classList.remove('border-red-800', 'bg-red-50');
            o.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
        applyThemePreview(theme);
    }

    function selectColor(color) {
        currentSettings.primary_color = color;
        document.querySelectorAll('.color-option > div').forEach(d => {
            d.classList.remove('ring-4', 'ring-offset-2');
            d.innerHTML = '';
        });
        const div = event.currentTarget.querySelector('div');
        div.classList.add('ring-4', 'ring-offset-2');
        div.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>';
        applyColorPreview(color);
    }

    function selectSidebarStyle(style) {
        currentSettings.sidebar_style = style;
        document.querySelectorAll('[onclick^="selectSidebarStyle"]').forEach(o => {
            o.classList.remove('border-red-800', 'bg-red-50');
            o.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('primary-gradient', style === 'gradient');
            sidebar.classList.toggle('primary-solid', style === 'solid');
        }
    }

    function selectFontSize(size) {
        currentSettings.font_size = size;
        document.querySelectorAll('[onclick^="selectFontSize"]').forEach(o => {
            o.classList.remove('border-red-800', 'bg-red-50');
            o.classList.add('border-gray-200');
        });
        event.currentTarget.classList.add('border-red-800', 'bg-red-50');
        event.currentTarget.classList.remove('border-gray-200');
        applyFontSizePreview(size);
    }

    // ── Preview Helpers ────────────────────────────────────────────
    function hexToRgb(hex) {
        const r = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return r ? { r: parseInt(r[1],16), g: parseInt(r[2],16), b: parseInt(r[3],16) } : null;
    }

    function applyColorPreview(color) {
        document.documentElement.style.setProperty('--primary-color', color);
        const rgb = hexToRgb(color);
        if (rgb) {
            document.documentElement.style.setProperty('--primary-rgb', `${rgb.r},${rgb.g},${rgb.b}`);
            document.documentElement.style.setProperty('--primary-dark-rgb', `${Math.max(0,rgb.r-40)},${Math.max(0,rgb.g-40)},${Math.max(0,rgb.b-40)}`);
        }
    }

    function applyThemePreview(theme) {
        document.body.classList.toggle('bg-gray-900', theme === 'dark');
        document.body.classList.toggle('bg-gray-50', theme !== 'dark');
    }

    function applyFontSizePreview(size) {
        document.documentElement.style.fontSize = { small:'14px', medium:'16px', large:'18px' }[size];
    }

    // ── Save / Reset Settings ──────────────────────────────────────
    async function saveSettings() {
        currentSettings.compact_mode           = document.getElementById('compactMode').checked;
        currentSettings.show_animations        = document.getElementById('showAnimations').checked;
        currentSettings.notifications_enabled  = document.getElementById('notificationsEnabled').checked;
        currentSettings.email_notifications    = document.getElementById('emailNotifications').checked;
        currentSettings.push_notifications     = document.getElementById('pushNotifications').checked;
        currentSettings.language               = document.getElementById('language').value;
        currentSettings.timezone               = document.getElementById('timezone').value;
        currentSettings.date_format            = document.getElementById('dateFormat').value;

        try {
            const res  = await fetch('/settings/preferences', {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(currentSettings)
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Settings saved successfully!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification('Failed to save settings.', 'error');
            }
        } catch (e) {
            showNotification('An error occurred while saving.', 'error');
        }
    }

    async function resetSettings() {
        if (!confirm('Reset all settings to default?')) return;
        try {
            const res  = await fetch('/settings/reset', {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Settings reset successfully!', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification('Failed to reset settings.', 'error');
            }
        } catch (e) {
            showNotification('An error occurred while resetting.', 'error');
        }
    }

    // ── Change Password ────────────────────────────────────────────
    async function changePassword(e) {
        e.preventDefault();
        const nw   = document.getElementById('newPassword').value.trim();
        const conf = document.getElementById('confirmPassword').value.trim();

        document.getElementById('confirmPasswordError').classList.add('hidden');

        if (!nw || !conf)   { showNotification('Please fill in all password fields.', 'warning'); return; }
        if (nw.length < 8)  { showNotification('New password must be at least 8 characters.', 'warning'); return; }
        if (nw !== conf) {
            const el = document.getElementById('confirmPasswordError');
            el.textContent = 'Passwords do not match.';
            el.classList.remove('hidden');
            return;
        }

        const btn  = document.getElementById('changePasswordBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg> Updating...';

        try {
            const res  = await fetch('/my-profile/change-password', {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ password: nw, password_confirmation: conf })
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Password updated successfully!', 'success');
                document.getElementById('changePasswordForm').reset();
            } else {
                showNotification(data.message || 'Failed to update password.', 'error');
            }
        } catch (err) {
            showNotification('An error occurred. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // ── Toggle Password Visibility ─────────────────────────────────
    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        // swap eye / eye-slash by swapping the path
        const eyeOpen  = 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z';
        const eyeSlash = 'M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88';
        const paths = btn.querySelectorAll('path');
        if (paths.length) paths[0].setAttribute('d', isHidden ? eyeSlash : eyeOpen);
    }

    // ── Notification Sound Picker ──────────────────────────────────
    var _previewAudio     = new Audio();
    var _defaultSoundFile = 'mixkit-software-interface-back-2575.wav';
    var _activeSoundWrap  = null;

    var SOUND_CHANNELS = [
        { key: 'notif_sound_ticket', wrapId: 'soundWrapTicket', panelId: 'soundPanelTicket', labelId: 'soundLabelTicket' },
        { key: 'notif_sound_chat',   wrapId: 'soundWrapChat',   panelId: 'soundPanelChat',   labelId: 'soundLabelChat'   },
    ];

    function _getCurrentSound(key) {
        var v = localStorage.getItem(key)
            || localStorage.getItem('notif_sound')
            || _defaultSoundFile;
        return v.includes('.') ? v : _defaultSoundFile;
    }

    function selectSound(key, filename) {
        if (!filename) return;
        localStorage.setItem(key, filename);
        previewSound(filename);
    }

    function previewSound(filename) {
        if (!filename) return;
        _previewAudio.src = '/sounds/' + filename;
        _previewAudio.currentTime = 0;
        _previewAudio.play().catch(function() {});
    }

    function _soundDDToggle(wrapId) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        if (_activeSoundWrap && _activeSoundWrap !== wrap) _soundDDHide(_activeSoundWrap);
        if (wrap.classList.contains('is-open')) _soundDDHide(wrap);
        else _soundDDShow(wrap);
    }

    function _soundDDShow(wrap) {
        var btn   = wrap.querySelector('.se-btn');
        var panel = wrap._soundPanel;
        if (!btn || !panel) return;
        var r = btn.getBoundingClientRect();
        var spaceBelow = window.innerHeight - r.bottom;
        var placeAbove = spaceBelow < 344 && r.top > spaceBelow;
        panel.style.left  = r.left + 'px';
        panel.style.width = r.width + 'px';
        if (placeAbove) {
            panel.style.top    = 'auto';
            panel.style.bottom = (window.innerHeight - r.top + 4) + 'px';
        } else {
            panel.style.bottom = 'auto';
            panel.style.top    = (r.bottom + 4) + 'px';
        }
        document.body.appendChild(panel);
        panel.classList.add('sound-dd-open');
        wrap.classList.add('is-open');
        _activeSoundWrap = wrap;
    }

    function _soundDDHide(wrap) {
        if (!wrap) return;
        wrap.classList.remove('is-open');
        var panel = wrap._soundPanel;
        if (panel) {
            panel.classList.remove('sound-dd-open');
            panel.style.cssText = '';
            if (panel.parentNode !== wrap) wrap.appendChild(panel);
        }
        if (_activeSoundWrap === wrap) _activeSoundWrap = null;
    }

    function _soundDDSelect(wrap, filename, text, channelKey) {
        wrap.dataset.value = filename;
        var label = wrap.querySelector('.se-label');
        if (label) { label.textContent = text; label.classList.remove('is-placeholder'); }
        var panel = wrap._soundPanel;
        if (panel) {
            panel.querySelectorAll('.sound-dd-item').forEach(function(item) {
                item.classList.toggle('is-active', item.dataset.value === filename);
            });
        }
        _soundDDHide(wrap);
        selectSound(channelKey, filename);
    }

    document.addEventListener('click', function(e) {
        if (!_activeSoundWrap) return;
        if (_activeSoundWrap.contains(e.target)) return;
        var panel = _activeSoundWrap._soundPanel;
        if (panel && panel.contains(e.target)) return;
        _soundDDHide(_activeSoundWrap);
    });

    window.addEventListener('scroll', function() {
        if (_activeSoundWrap) _soundDDHide(_activeSoundWrap);
    }, true);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && _activeSoundWrap) _soundDDHide(_activeSoundWrap);
    });

    async function _loadSounds() {
        try {
            var res  = await fetch('/api/notification-sounds', { credentials: 'same-origin' });
            var json = await res.json();
            if (!json.success || !json.data.length) return;

            var def = json.data.find(function(s) { return s.is_default; });
            if (def) _defaultSoundFile = def.filename;

            SOUND_CHANNELS.forEach(function(channel) {
                var wrap  = document.getElementById(channel.wrapId);
                var panel = document.getElementById(channel.panelId);
                var label = document.getElementById(channel.labelId);
                if (!wrap || !panel || !label) return;

                wrap._soundPanel = panel;

                var current = _getCurrentSound(channel.key);
                panel.innerHTML = '';
                json.data.forEach(function(s) {
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'sound-dd-item' + (s.filename === current ? ' is-active' : '');
                    item.textContent = s.name + (s.is_default ? ' (Default)' : '');
                    item.dataset.value = s.filename;
                    (function(w, fn, txt, k) {
                        item.addEventListener('click', function(e) {
                            e.stopPropagation();
                            _soundDDSelect(w, fn, txt, k);
                        });
                    })(wrap, s.filename, item.textContent, channel.key);
                    panel.appendChild(item);
                });

                var currentSound = json.data.find(function(s) { return s.filename === current; })
                                || json.data[0];
                if (currentSound) {
                    wrap.dataset.value = currentSound.filename;
                    label.textContent  = currentSound.name + (currentSound.is_default ? ' (Default)' : '');
                    label.classList.remove('is-placeholder');
                }
            });
        } catch (e) {
            console.warn('Failed to load sounds', e);
        }
    }

    _loadSounds();

    // ── Expose ─────────────────────────────────────────────────────
    window.switchTab          = switchTab;
    window.selectTheme        = selectTheme;
    window.selectColor        = selectColor;
    window.selectSidebarStyle = selectSidebarStyle;
    window.selectFontSize     = selectFontSize;
    window.saveSettings       = saveSettings;
    window.resetSettings      = resetSettings;
    window.changePassword     = changePassword;
    window.togglePassword     = togglePassword;
    window.selectSound        = selectSound;
    window.previewSound       = previewSound;
    window._soundDDToggle     = _soundDDToggle;
})();
</script>
@endsection

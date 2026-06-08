@extends('dashboard')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Manage your workspace preferences and account security')

@section('content')

<div class="flex gap-0 items-start bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden min-h-[600px]">

    {{-- ── Left Nav ──────────────────────────────────────────────────────────── --}}
    <aside class="w-48 flex-shrink-0 border-r border-gray-100 py-3">
        <nav>
            @php
            $navItems = [
                ['id'=>'appearance',    'icon'=>'fa-paint-brush', 'label'=>'Appearance'],
                ['id'=>'preferences',   'icon'=>'fa-sliders-h',   'label'=>'Preferences'],
                ['id'=>'notifications', 'icon'=>'fa-bell',        'label'=>'Notifications'],
                ['id'=>'security',      'icon'=>'fa-shield-alt',  'label'=>'Security'],
                ['id'=>'language',      'icon'=>'fa-globe',       'label'=>'Language & Region'],
                ['id'=>'about',         'icon'=>'fa-info-circle', 'label'=>'About'],
            ];
            @endphp
            <ul class="space-y-0.5 px-2">
                @foreach($navItems as $n)
                <li>
                    <button onclick="switchTab('{{ $n['id'] }}')" data-tab="{{ $n['id'] }}"
                        class="settings-tab w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-left transition-all text-sm
                               {{ $loop->first
                                  ? 'bg-red-50 text-red-700 font-semibold'
                                  : 'text-gray-600 font-medium hover:bg-gray-50 hover:text-gray-800' }}">
                        <i class="fas {{ $n['icon'] }} w-4 text-center flex-shrink-0 text-xs
                                   {{ $loop->first ? 'text-red-600' : 'text-gray-400' }}"></i>
                        <span class="truncate">{{ $n['label'] }}</span>
                    </button>
                </li>
                @endforeach
            </ul>
        </nav>
    </aside>

    {{-- ── Content ───────────────────────────────────────────────────────────── --}}
    <div class="flex-1 min-w-0">

        {{-- ══════════════════════════════════════════════════════
             APPEARANCE
        ══════════════════════════════════════════════════════ --}}
        <div id="tab-appearance" class="settings-content">

            {{-- Top bar --}}
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Appearance</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Customize how ECoSystem looks for you</p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="resetSettings()"
                        class="px-3 py-1.5 text-xs font-medium text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        Reset
                    </button>
                    <button onclick="saveSettings()"
                        class="px-4 py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg transition">
                        Save changes
                    </button>
                </div>
            </div>

            <div class="divide-y divide-gray-100">

                {{-- Theme Mode --}}
                <div class="px-8 py-6">
                    <div class="flex items-start justify-between gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Theme</p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">Choose how the interface looks to you</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @foreach(['light'=>['Light','fa-sun','bg-white border border-gray-200','text-yellow-500'],
                                      'dark' =>['Dark', 'fa-moon','bg-gray-900','text-blue-400'],
                                      'auto' =>['Auto', 'fa-desktop','bg-gradient-to-r from-gray-900 to-white','text-gray-400']]
                                     as $tk=>[$tl,$ti,$tBg,$tiC])
                            <button type="button" onclick="selectTheme('{{ $tk }}')"
                                class="theme-option flex flex-col items-center gap-2 w-24 p-2.5 border-2 rounded-xl transition-all hover:border-red-400
                                       {{ $preferences['theme']===$tk ? 'border-red-600 bg-red-50/50' : 'border-gray-200' }}">
                                <div class="w-full h-8 rounded-lg {{ $tBg }} flex items-center justify-center overflow-hidden">
                                    <i class="fas {{ $ti }} {{ $tiC }}"></i>
                                </div>
                                <span class="text-xs font-medium {{ $preferences['theme']===$tk ? 'text-red-700' : 'text-gray-500' }}">{{ $tl }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Accent Color --}}
                <div class="px-8 py-6">
                    <div class="flex items-start justify-between gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Accent color</p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">Used for buttons and active states</p>
                        </div>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach(['#c62828'=>'Red','#1976d2'=>'Blue','#388e3c'=>'Green','#f57c00'=>'Orange',
                                      '#7b1fa2'=>'Purple','#0097a7'=>'Teal','#5d4037'=>'Brown','#455a64'=>'Slate']
                                     as $hex=>$label)
                            <button type="button" onclick="selectColor('{{ $hex }}')" title="{{ $label }}"
                                class="color-option w-8 h-8 rounded-lg flex items-center justify-center transition-all hover:scale-110 shadow-sm
                                       {{ $preferences['primary_color']===$hex ? 'ring-2 ring-offset-2 ring-gray-400 scale-110' : '' }}"
                                style="background:{{ $hex }}">
                                @if($preferences['primary_color']===$hex)
                                    <i class="fas fa-check text-white text-xs"></i>
                                @endif
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Sidebar Style --}}
                <div class="px-8 py-6">
                    <div class="flex items-start justify-between gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Sidebar style</p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">Background of the left navigation</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @foreach(['gradient'=>['Gradient','bg-gradient-to-br from-red-800 to-red-950'],
                                      'solid'   =>['Solid',   'bg-red-800']] as $sk=>[$sl,$sBg])
                            <button type="button" onclick="selectSidebarStyle('{{ $sk }}')"
                                class="flex flex-col items-center gap-2 w-24 p-2.5 border-2 rounded-xl transition-all hover:border-red-400
                                       {{ $preferences['sidebar_style']===$sk ? 'border-red-600 bg-red-50/50' : 'border-gray-200' }}">
                                <div class="w-full h-8 rounded-lg {{ $sBg }}"></div>
                                <span class="text-xs font-medium {{ $preferences['sidebar_style']===$sk ? 'text-red-700' : 'text-gray-500' }}">{{ $sl }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Font Size --}}
                <div class="px-8 py-6">
                    <div class="flex items-start justify-between gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Font size</p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">Text size across the interface</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @foreach(['small'=>['Small','text-sm'],'medium'=>['Medium','text-base'],'large'=>['Large','text-lg']] as $sk=>[$sl,$cls])
                            <button type="button" onclick="selectFontSize('{{ $sk }}')"
                                class="flex flex-col items-center gap-1.5 w-20 p-2.5 border-2 rounded-xl transition-all hover:border-red-400
                                       {{ $preferences['font_size']===$sk ? 'border-red-600 bg-red-50/50' : 'border-gray-200' }}">
                                <span class="{{ $cls }} font-bold {{ $preferences['font_size']===$sk ? 'text-red-700' : 'text-gray-600' }}">Aa</span>
                                <span class="text-[10px] {{ $preferences['font_size']===$sk ? 'text-red-600' : 'text-gray-400' }}">{{ $sl }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Toggles --}}
                @foreach([
                    ['compactMode',    'Compact mode',    'Reduce spacing and padding throughout the UI',       $preferences['compact_mode']],
                    ['showAnimations', 'Show animations', 'Enable smooth transitions and motion effects',        $preferences['show_animations']],
                ] as [$id,$title,$desc,$checked])
                <div class="px-8 py-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-8">
                        <input type="checkbox" id="{{ $id }}" class="sr-only peer" {{ $checked ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-[17px] after:w-[17px] after:transition-all peer-checked:bg-red-700"></div>
                    </label>
                </div>
                @endforeach

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             PREFERENCES
        ══════════════════════════════════════════════════════ --}}
        <div id="tab-preferences" class="settings-content hidden">
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Preferences</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Control how the application behaves</p>
                </div>
                <button onclick="saveSettings()"
                    class="px-4 py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg transition">
                    Save changes
                </button>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach([
                    ['autoSave',         'fa-save',    'Auto-save',          'Automatically save your work in progress',        true],
                    ['autoRefresh',      'fa-sync-alt','Auto-refresh',       'Refresh data automatically in the background',    false],
                    ['keyboardShortcuts','fa-keyboard','Keyboard shortcuts',  'Enable global keyboard shortcut bindings',        true],
                ] as [$id,$icon,$title,$desc,$checked])
                <div class="px-8 py-5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas {{ $icon }} text-gray-300 w-4 text-center text-sm flex-shrink-0"></i>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-8">
                        <input type="checkbox" id="{{ $id }}" class="sr-only peer" {{ $checked ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-[17px] after:w-[17px] after:transition-all peer-checked:bg-red-700"></div>
                    </label>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             NOTIFICATIONS
        ══════════════════════════════════════════════════════ --}}
        <div id="tab-notifications" class="settings-content hidden">
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Notifications</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Control how and when you receive alerts</p>
                </div>
                <button onclick="saveSettings()"
                    class="px-4 py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg transition">
                    Save changes
                </button>
            </div>
            <div class="divide-y divide-gray-100">

                {{-- Master toggle --}}
                <div class="px-8 py-5 flex items-center justify-between bg-gray-50/60">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Enable notifications</p>
                        <p class="text-xs text-gray-400 mt-0.5">Master switch — controls all notification types</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-8">
                        <input type="checkbox" id="notificationsEnabled" class="sr-only peer" {{ $preferences['notifications_enabled'] ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-[17px] after:w-[17px] after:transition-all peer-checked:bg-red-700"></div>
                    </label>
                </div>

                @foreach([
                    ['emailNotifications','fa-envelope',   'Email notifications',  'Receive notifications via email',          $preferences['email_notifications']],
                    ['pushNotifications', 'fa-mobile-alt', 'Push notifications',   'Browser and device push alerts',           $preferences['push_notifications']],
                ] as [$id,$icon,$title,$desc,$checked])
                <div class="px-8 py-5 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i class="fas {{ $icon }} text-gray-300 w-4 text-center text-sm flex-shrink-0"></i>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-8">
                        <input type="checkbox" id="{{ $id }}" class="sr-only peer" {{ $checked ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-[17px] after:w-[17px] after:transition-all peer-checked:bg-red-700"></div>
                    </label>
                </div>
                @endforeach

                {{-- Sound divider --}}
                <div class="px-8 pt-6 pb-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Notification sounds</p>
                </div>

                @foreach([
                    ['soundWrapTicket','soundPanelTicket','soundLabelTicket','notif_sound_ticket','New ticket / alert',  'Badge notifications and new ticket alerts'],
                    ['soundWrapChat',  'soundPanelChat',  'soundLabelChat',  'notif_sound_chat',  'Message / reply',     'Incoming replies and chat messages'],
                ] as [$wrapId,$panelId,$labelId,$key,$title,$desc])
                <div class="px-8 py-5 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="se-wrap w-44" id="{{ $wrapId }}">
                            <button type="button" class="se-btn w-full flex items-center justify-between px-3 py-2 text-xs border border-gray-200 rounded-lg bg-white hover:border-gray-300 transition text-gray-700"
                                onclick="_soundDDToggle('{{ $wrapId }}')">
                                <span class="se-label is-placeholder truncate" id="{{ $labelId }}">Loading…</span>
                                <i class="fas fa-chevron-down text-gray-400 text-[10px] se-arrow ml-2 flex-shrink-0"></i>
                            </button>
                            <div class="sound-dd-panel" id="{{ $panelId }}" role="listbox"></div>
                        </div>
                        <button type="button" title="Preview"
                            onclick="previewSound(document.getElementById('{{ $wrapId }}').dataset.value)"
                            class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-300 hover:bg-red-50 transition">
                            <i class="fas fa-play text-xs"></i>
                        </button>
                    </div>
                </div>
                @endforeach

                <div class="px-8 py-4">
                    <p class="text-xs text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        Sound settings are stored in your browser. Use the 🔊 icon in the header to toggle sound on/off.
                    </p>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             SECURITY
        ══════════════════════════════════════════════════════ --}}
        <div id="tab-security" class="settings-content hidden">
            <div class="px-8 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Security</h2>
                <p class="text-xs text-gray-400 mt-0.5">Manage your password and account access</p>
            </div>
            <div class="divide-y divide-gray-100">

                {{-- Change Password --}}
                <div class="px-8 py-6">
                    <div class="flex items-start gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Password</p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">Update your account password</p>
                        </div>
                        <form id="changePasswordForm" onsubmit="changePassword(event)" class="flex-1 max-w-sm space-y-3">
                            <div class="relative">
                                <input type="password" id="newPassword"
                                    class="w-full px-3 py-2.5 pr-9 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300 focus:bg-white transition"
                                    placeholder="New password (min. 8 characters)">
                                <button type="button" onclick="togglePassword('newPassword',this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i class="fas fa-eye text-xs eye-icon"></i>
                                </button>
                            </div>
                            <div class="relative">
                                <input type="password" id="confirmPassword"
                                    class="w-full px-3 py-2.5 pr-9 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300 focus:bg-white transition"
                                    placeholder="Confirm new password">
                                <button type="button" onclick="togglePassword('confirmPassword',this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                                    <i class="fas fa-eye text-xs eye-icon"></i>
                                </button>
                            </div>
                            <p id="confirmPasswordError" class="text-xs text-red-500 hidden"></p>
                            <button type="submit" id="changePasswordBtn"
                                class="px-4 py-2 text-xs font-semibold text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition">
                                Update password
                            </button>
                        </form>
                    </div>
                </div>

                {{-- 2FA --}}
                <div class="px-8 py-5 flex items-center justify-between">
                    <div class="flex items-start gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Two-factor authentication</p>
                            <p class="text-xs text-gray-400 mt-0.5">Add an extra layer of security to your account</p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Not configured
                        </span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 ml-8">
                        <input type="checkbox" id="twoFactorAuth" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-[17px] after:w-[17px] after:transition-all peer-checked:bg-red-700"></div>
                    </label>
                </div>

                {{-- Active Session --}}
                <div class="px-8 py-6">
                    <div class="flex items-start gap-8">
                        <div class="w-48 flex-shrink-0">
                            <p class="text-sm font-medium text-gray-800">Active sessions</p>
                            <p class="text-xs text-gray-400 mt-1 leading-relaxed">Manage where you're logged in</p>
                        </div>
                        <div class="flex-1 max-w-sm">
                            <div class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-lg mb-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-desktop text-green-600 text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-gray-700">Current session</p>
                                        <p class="text-[10px] text-gray-400">{{ request()->ip() }} &middot; Active now</p>
                                    </div>
                                </div>
                                <span class="flex items-center gap-1 text-[10px] font-semibold text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Active
                                </span>
                            </div>
                            @if(session('user.role.id') === 1)
                            <a href="{{ route('admin.sessions') }}"
                                class="text-xs font-medium text-gray-500 hover:text-gray-700 underline underline-offset-2 transition">
                                Manage all sessions →
                            </a>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             LANGUAGE & REGION
        ══════════════════════════════════════════════════════ --}}
        <div id="tab-language" class="settings-content hidden">
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Language &amp; Region</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Set your language and regional preferences</p>
                </div>
                <button onclick="saveSettings()"
                    class="px-4 py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg transition">
                    Save changes
                </button>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach([
                    ['language',   'Language',    'The language used throughout the interface',
                     [['en','🇬🇧  English'],['id','🇮🇩  Bahasa Indonesia']],  $preferences['language']],
                    ['timezone',   'Timezone',    'Your local timezone for displaying dates and times',
                     [['Asia/Jakarta','Asia/Jakarta (WIB, UTC+7)'],['Asia/Singapore','Asia/Singapore (SGT, UTC+8)'],['UTC','UTC']], $preferences['timezone']],
                    ['dateFormat', 'Date format', 'How dates are displayed across the application',
                     [['DD/MM/YYYY','DD/MM/YYYY'],['MM/DD/YYYY','MM/DD/YYYY'],['YYYY-MM-DD','YYYY-MM-DD (ISO 8601)']], $preferences['date_format']],
                ] as [$id,$title,$desc,$opts,$current])
                <div class="px-8 py-5 flex items-center justify-between gap-8">
                    <div class="w-48 flex-shrink-0">
                        <p class="text-sm font-medium text-gray-800">{{ $title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $desc }}</p>
                    </div>
                    <select id="{{ $id }}"
                        class="w-52 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-300 transition">
                        @foreach($opts as [$val,$lbl])
                        <option value="{{ $val }}" {{ $current===$val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════
             ABOUT
        ══════════════════════════════════════════════════════ --}}
        <div id="tab-about" class="settings-content hidden">

            {{-- Hero --}}
            <div class="bg-gradient-to-br from-red-800 to-red-950 px-8 py-8 flex items-center gap-5">
                <div class="w-14 h-14 bg-white/10 border border-white/20 rounded-2xl flex items-center justify-center overflow-hidden flex-shrink-0">
                    <img src="/images/logo_nobg.png" alt="ECoSystem" class="w-10 h-10 object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">ECoSystem</h1>
                    <p class="text-red-200 text-xs mt-0.5">Enterprise Management Platform</p>
                    <div class="flex items-center gap-2 mt-2.5">
                        <span class="text-xs font-medium px-2.5 py-0.5 bg-white/15 border border-white/20 text-white rounded-full">v1.0.0</span>
                        <span class="text-xs font-medium px-2.5 py-0.5 bg-emerald-500/20 border border-emerald-400/25 text-emerald-300 rounded-full flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>Up to date
                        </span>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-gray-100">

                {{-- Meta --}}
                @foreach([
                    ['fa-calendar-alt','Release date',  'November 2025'],
                    ['fa-sync-alt',    'Last updated',  now()->format('d M Y')],
                    ['fa-certificate', 'License',       'Enterprise'],
                    ['fa-code-branch', 'Build',         'Production'],
                ] as [$icon,$label,$value])
                <div class="px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i class="fas {{ $icon }} text-gray-300 w-4 text-center text-xs flex-shrink-0"></i>
                        <span class="text-sm text-gray-600">{{ $label }}</span>
                    </div>
                    <span class="text-sm font-medium text-gray-800">{{ $value }}</span>
                </div>
                @endforeach

                {{-- Modules --}}
                <div class="px-8 py-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Platform modules</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach([
                            ['fa-ticket-alt',     'Ticket Management'],
                            ['fa-headset',        'Delivery Support'],
                            ['fa-project-diagram','Delivery Project'],
                            ['fa-clock',          'Timesheet'],
                            ['fa-chart-bar',      'Reporting'],
                            ['fa-users',          'HR & Employees'],
                            ['fa-building',       'Customer Management'],
                            ['fa-stopwatch',      'SLA Management'],
                        ] as [$icon,$mod])
                        <div class="flex items-center gap-2 text-xs text-gray-600 py-1">
                            <i class="fas {{ $icon }} text-gray-300 w-4 text-center text-xs"></i>
                            {{ $mod }}
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-8 py-5 flex items-center justify-between bg-gray-50/50">
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <span>Built by</span>
                        @foreach([['P','Putra'],['N','Natanael']] as [$i,$n])
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-white border border-gray-200 text-gray-600 font-medium rounded-full">
                            <span class="w-4 h-4 bg-red-700 text-white rounded-full flex items-center justify-center text-[9px] font-bold">{{ $i }}</span>
                            {{ $n }}
                        </span>
                        @endforeach
                    </div>
                    <span class="text-xs text-gray-400">&copy; {{ now()->year }} PT Eclectic Consulting</span>
                </div>
            </div>
        </div>

    </div>{{-- end content --}}
</div>{{-- end page --}}

<style>
.se-btn { display:flex; align-items:center; }
.se-label.is-placeholder { color:#9ca3af; }
.se-wrap.is-open .se-arrow { transform:rotate(180deg); }
.se-arrow { transition:transform .15s ease; }

.sound-dd-panel {
    display:none; background:#fff; border:1px solid #e5e7eb;
    border-radius:.625rem; box-shadow:0 8px 30px rgba(0,0,0,.12);
    max-height:260px; overflow-y:auto; padding:.25rem 0;
}
.sound-dd-panel.sound-dd-open { display:block; position:fixed; z-index:9999; }
.sound-dd-item {
    display:block; width:100%; padding:.45rem .875rem;
    font-size:.8rem; color:#374151; text-align:left;
    background:transparent; border:0; cursor:pointer;
}
.sound-dd-item:hover   { background:#f9fafb; }
.sound-dd-item.is-active { background:#fef2f2; color:#991b1b; font-weight:600; }
</style>

<script>
(function(){
'use strict';
let S = @json($preferences);

// ── Tab ───────────────────────────────────────────────────────────────────────
function switchTab(name) {
    document.querySelectorAll('.settings-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.classList.remove('bg-red-50','text-red-700','font-semibold');
        btn.classList.add('text-gray-600','font-medium');
        const ic = btn.querySelector('.fas');
        if (ic) { ic.classList.remove('text-red-600'); ic.classList.add('text-gray-400'); }
    });
    document.getElementById('tab-'+name)?.classList.remove('hidden');
    const a = document.querySelector(`[data-tab="${name}"]`);
    if (a) {
        a.classList.add('bg-red-50','text-red-700','font-semibold');
        a.classList.remove('text-gray-600','font-medium');
        const ic = a.querySelector('.fas');
        if (ic) { ic.classList.add('text-red-600'); ic.classList.remove('text-gray-400'); }
    }
}

// ── Appearance ────────────────────────────────────────────────────────────────
function selectTheme(theme) {
    S.theme = theme;
    document.querySelectorAll('.theme-option').forEach(o => {
        o.classList.remove('border-red-600','bg-red-50/50');
        o.classList.add('border-gray-200');
        o.querySelector('span')?.classList.replace('text-red-700','text-gray-500');
    });
    const el = event.currentTarget;
    el.classList.add('border-red-600','bg-red-50/50');
    el.classList.remove('border-gray-200');
    el.querySelector('span')?.classList.replace('text-gray-500','text-red-700');
}
function selectColor(hex) {
    S.primary_color = hex;
    document.querySelectorAll('.color-option').forEach(b => {
        b.classList.remove('ring-2','ring-offset-2','ring-gray-400','scale-110');
        b.innerHTML = '';
    });
    const el = event.currentTarget;
    el.classList.add('ring-2','ring-offset-2','ring-gray-400','scale-110');
    el.innerHTML = '<i class="fas fa-check text-white text-xs"></i>';
    _applyColor(hex);
}
function selectSidebarStyle(style) {
    S.sidebar_style = style;
    document.querySelectorAll('[onclick^="selectSidebarStyle"]').forEach(o => {
        o.classList.remove('border-red-600','bg-red-50/50');
        o.classList.add('border-gray-200');
        o.querySelectorAll('span').forEach(s => { s.classList.replace('text-red-700','text-gray-500'); });
    });
    const el = event.currentTarget;
    el.classList.add('border-red-600','bg-red-50/50');
    el.classList.remove('border-gray-200');
    el.querySelectorAll('span').forEach(s => { s.classList.replace('text-gray-500','text-red-700'); });
    const sb = document.getElementById('sidebar');
    if (sb) { sb.classList.toggle('primary-gradient', style==='gradient'); sb.classList.toggle('primary-solid', style==='solid'); }
}
function selectFontSize(size) {
    S.font_size = size;
    document.querySelectorAll('[onclick^="selectFontSize"]').forEach(o => {
        o.classList.remove('border-red-600','bg-red-50/50');
        o.classList.add('border-gray-200');
    });
    const el = event.currentTarget;
    el.classList.add('border-red-600','bg-red-50/50');
    el.classList.remove('border-gray-200');
    document.documentElement.style.fontSize = {small:'14px',medium:'16px',large:'18px'}[size];
}
function _applyColor(hex) {
    document.documentElement.style.setProperty('--primary-color', hex);
    const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    if (m) {
        const [r,g,b] = [parseInt(m[1],16),parseInt(m[2],16),parseInt(m[3],16)];
        document.documentElement.style.setProperty('--primary-rgb',`${r},${g},${b}`);
        document.documentElement.style.setProperty('--primary-dark-rgb',`${Math.max(0,r-40)},${Math.max(0,g-40)},${Math.max(0,b-40)}`);
    }
}

// ── Save / Reset ──────────────────────────────────────────────────────────────
async function saveSettings() {
    S.compact_mode          = document.getElementById('compactMode').checked;
    S.show_animations       = document.getElementById('showAnimations').checked;
    S.notifications_enabled = document.getElementById('notificationsEnabled').checked;
    S.email_notifications   = document.getElementById('emailNotifications').checked;
    S.push_notifications    = document.getElementById('pushNotifications').checked;
    S.language              = document.getElementById('language').value;
    S.timezone              = document.getElementById('timezone').value;
    S.date_format           = document.getElementById('dateFormat').value;
    try {
        const r = await fetch('/settings/preferences',{
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':_csrf()},
            body:JSON.stringify(S)
        });
        const d = await r.json();
        d.success ? (showNotification('Settings saved!','success'), setTimeout(()=>location.reload(),800))
                  : showNotification('Failed to save.','error');
    } catch { showNotification('Network error.','error'); }
}
async function resetSettings() {
    if (!confirm('Reset all settings to default values?')) return;
    try {
        const r = await fetch('/settings/reset',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':_csrf()}});
        const d = await r.json();
        d.success ? (showNotification('Reset to default.','success'), setTimeout(()=>location.reload(),800))
                  : showNotification('Failed to reset.','error');
    } catch { showNotification('Network error.','error'); }
}

// ── Password ──────────────────────────────────────────────────────────────────
async function changePassword(e) {
    e.preventDefault();
    const nw = document.getElementById('newPassword').value.trim();
    const cf = document.getElementById('confirmPassword').value.trim();
    const errEl = document.getElementById('confirmPasswordError');
    errEl.classList.add('hidden');
    if (!nw||!cf)      { showNotification('Please fill in all fields.','warning'); return; }
    if (nw.length < 8) { showNotification('Password must be at least 8 characters.','warning'); return; }
    if (nw !== cf)     { errEl.textContent = 'Passwords do not match.'; errEl.classList.remove('hidden'); return; }
    const btn = document.getElementById('changePasswordBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i>Updating…';
    try {
        const r = await fetch('/my-profile/change-password',{
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':_csrf()},
            body:JSON.stringify({password:nw,password_confirmation:cf})
        });
        const d = await r.json();
        d.success ? (showNotification('Password updated!','success'), document.getElementById('changePasswordForm').reset())
                  : showNotification(d.message||'Failed.','error');
    } catch { showNotification('Network error.','error'); }
    finally { btn.disabled=false; btn.innerHTML=orig; }
}
function togglePassword(id, btn) {
    const inp = document.getElementById(id);
    const hidden = inp.type === 'password';
    inp.type = hidden ? 'text' : 'password';
    const ic = btn.querySelector('.fa');
    if (ic) { ic.classList.toggle('fa-eye',!hidden); ic.classList.toggle('fa-eye-slash',hidden); }
}

// ── Sound picker ──────────────────────────────────────────────────────────────
var _pa = new Audio(), _def = 'mixkit-software-interface-back-2575.wav', _aw = null;
var SC = [
    {key:'notif_sound_ticket',wrapId:'soundWrapTicket',panelId:'soundPanelTicket',labelId:'soundLabelTicket'},
    {key:'notif_sound_chat',  wrapId:'soundWrapChat',  panelId:'soundPanelChat',  labelId:'soundLabelChat'},
];
function _gs(key){ var v=localStorage.getItem(key)||localStorage.getItem('notif_sound')||_def; return v.includes('.')?v:_def; }
function selectSound(k,f){ if(!f) return; localStorage.setItem(k,f); previewSound(f); }
function previewSound(f){ if(!f) return; _pa.src='/sounds/'+f; _pa.currentTime=0; _pa.play().catch(()=>{}); }
function _soundDDToggle(wId){
    var w=document.getElementById(wId); if(!w) return;
    if(_aw&&_aw!==w) _sdh(_aw);
    w.classList.contains('is-open')?_sdh(w):_sds(w);
}
function _sds(w){
    var btn=w.querySelector('.se-btn'), p=w._soundPanel; if(!btn||!p) return;
    var r=btn.getBoundingClientRect(), sb=window.innerHeight-r.bottom;
    p.style.left=r.left+'px'; p.style.width=r.width+'px';
    if(sb<264&&r.top>sb){p.style.top='auto';p.style.bottom=(window.innerHeight-r.top+4)+'px';}
    else{p.style.bottom='auto';p.style.top=(r.bottom+4)+'px';}
    document.body.appendChild(p); p.classList.add('sound-dd-open'); w.classList.add('is-open'); _aw=w;
}
function _sdh(w){
    if(!w) return; w.classList.remove('is-open');
    var p=w._soundPanel; if(p){p.classList.remove('sound-dd-open');p.style.cssText='';if(p.parentNode!==w)w.appendChild(p);}
    if(_aw===w) _aw=null;
}
function _sdsel(w,f,t,k){ w.dataset.value=f; var l=w.querySelector('.se-label'); if(l){l.textContent=t;l.classList.remove('is-placeholder');} var p=w._soundPanel; if(p)p.querySelectorAll('.sound-dd-item').forEach(i=>i.classList.toggle('is-active',i.dataset.value===f)); _sdh(w); selectSound(k,f); }
document.addEventListener('click',e=>{if(!_aw||_aw.contains(e.target)||_aw._soundPanel?.contains(e.target))return;_sdh(_aw);});
window.addEventListener('scroll',()=>{if(_aw)_sdh(_aw);},true);
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&_aw)_sdh(_aw);});
async function _loadSounds(){
    try{
        var r=await fetch('/api/notification-sounds',{credentials:'same-origin'});
        var j=await r.json(); if(!j.success||!j.data.length) return;
        var d=j.data.find(s=>s.is_default); if(d)_def=d.filename;
        SC.forEach(ch=>{
            var w=document.getElementById(ch.wrapId),p=document.getElementById(ch.panelId),l=document.getElementById(ch.labelId);
            if(!w||!p||!l) return; w._soundPanel=p;
            var cur=_gs(ch.key); p.innerHTML='';
            j.data.forEach(s=>{
                var it=document.createElement('button'); it.type='button';
                it.className='sound-dd-item'+(s.filename===cur?' is-active':'');
                it.textContent=s.name+(s.is_default?' (Default)':''); it.dataset.value=s.filename;
                it.addEventListener('click',e=>{e.stopPropagation();_sdsel(w,s.filename,it.textContent,ch.key);});
                p.appendChild(it);
            });
            var cs=j.data.find(s=>s.filename===cur)||j.data[0];
            if(cs){w.dataset.value=cs.filename;l.textContent=cs.name+(cs.is_default?' (Default)':'');l.classList.remove('is-placeholder');}
        });
    }catch(e){console.warn('sounds fail',e);}
}
_loadSounds();

function _csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }

// ── Expose ────────────────────────────────────────────────────────────────────
window.switchTab=switchTab; window.selectTheme=selectTheme; window.selectColor=selectColor;
window.selectSidebarStyle=selectSidebarStyle; window.selectFontSize=selectFontSize;
window.saveSettings=saveSettings; window.resetSettings=resetSettings;
window.changePassword=changePassword; window.togglePassword=togglePassword;
window.selectSound=selectSound; window.previewSound=previewSound; window._soundDDToggle=_soundDDToggle;
})();
</script>
@endsection

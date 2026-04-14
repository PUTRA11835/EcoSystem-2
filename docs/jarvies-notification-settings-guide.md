# Jarvies — Panduan Notifikasi, Settings, dan Layout Konsisten

> **Tujuan dokumen ini:**  
> Menjelaskan bagaimana EcoSystem mengimplementasikan notifikasi bell, menu settings,
> dan layout keseluruhan — beserta panduan adaptasi di Jarvies agar tampilan keduanya
> konsisten meskipun memiliki tujuan yang berbeda.

---

## Daftar Isi

1. [Struktur Layout (Sidebar + Header)](#1-struktur-layout)
2. [CSS Custom Properties (Theming)](#2-css-custom-properties)
3. [Toast Notification System](#3-toast-notification-system)
4. [Notification Bell](#4-notification-bell)
5. [Settings Menu](#5-settings-menu)
6. [Adaptasi Jarvies — Notifikasi Update Tiket](#6-adaptasi-jarvies)
7. [Checklist Implementasi Jarvies](#7-checklist)

---

## 1. Struktur Layout

EcoSystem menggunakan layout dua-kolom: **sidebar kiri** + **main content kanan**.

```
┌──────────┬─────────────────────────────────────┐
│          │  HEADER (sticky)                     │
│ SIDEBAR  │  [☰ Title] ── [🔔 Bell] [User Menu] │
│ (w-64)   ├─────────────────────────────────────┤
│          │                                      │
│ Nav Links│  CONTENT AREA (@yield('content'))   │
│          │                                      │
└──────────┴─────────────────────────────────────┘
```

### HTML Skeleton

```html
<div class="flex min-h-screen" style="background-color: var(--bg-color);">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="sidebar-transition w-64 flex-shrink-0 fixed top-0 left-0 h-full z-50 shadow-xl overflow-y-auto"
           style="background: /* primary gradient or solid, see Section 2 */">

        <!-- Logo Area -->
        <div class="px-6 py-5 border-b border-white/10">
            <div class="logo-expanded flex items-center gap-3">
                <img src="/images/logo.png" alt="Logo" class="h-10 w-auto">
                <span class="text-white font-bold text-xl">JARVIES</span>
            </div>
            <!-- Collapsed icon only (hidden by default) -->
            <div class="logo-collapsed hidden">
                <img src="/images/logo-icon.png" alt="Logo" class="h-10 w-10">
            </div>
        </div>

        <!-- Navigation -->
        <nav class="px-4 py-6 space-y-1">
            <!-- Nav link pattern -->
            <a href="/dashboard"
               class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-white/80 hover:text-white hover:bg-white/15 transition-all text-sm font-medium
                      /* active state: */ bg-white/20 text-white font-semibold shadow-lg">
                <i class="fas fa-home w-5 text-center flex-shrink-0"></i>
                <span class="nav-text font-medium">Dashboard</span>
            </a>
            <!-- More nav links... -->
        </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main id="mainContent" class="sidebar-transition flex-1 ml-64 min-w-0 overflow-x-hidden">

        <!-- HEADER (sticky) -->
        <header class="sticky top-0 z-40 shadow-sm border-b border-gray-100"
                style="background-color: var(--card-bg);">
            <div class="px-6 py-4 flex justify-between items-center">

                <!-- Left: hamburger + title -->
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()"
                        class="w-10 h-10 flex items-center justify-center border-2 rounded-xl
                               hover:bg-opacity-10 transition-all"
                        style="border-color: var(--primary-color); color: var(--text-color);">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="text-xl font-bold mb-0.5" style="color: var(--text-color);">
                            <!-- Page title -->
                        </h1>
                        <p class="text-xs text-gray-500"><!-- Subtitle --></p>
                    </div>
                </div>

                <!-- Right: bell + user menu -->
                <div class="flex items-center gap-4">
                    <!-- Notification Bell (lihat Section 4) -->
                    <!-- User Menu (lihat Section 1.1) -->
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="p-8">
            <!-- Content here -->
        </div>
    </main>
</div>
```

### 1.1 User Menu Dropdown

```html
<div class="relative">
    <button onclick="toggleUserDropdown()"
        class="flex items-center gap-3 px-4 py-2.5 border-2 border-gray-200 rounded-xl
               hover:bg-gray-50 hover:border-red-800 transition-all">
        <!-- Avatar initials -->
        <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center
                    font-bold text-sm shadow-md"
             style="background: linear-gradient(135deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb)));">
            <!-- 2 huruf nama/perusahaan -->
        </div>
        <div class="text-left hidden xl:block">
            <div class="text-sm font-bold text-gray-900">Nama User</div>
            <div class="text-xs text-gray-500">Role</div>
        </div>
        <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
    </button>

    <div id="userDropdown"
         class="hidden absolute top-full right-0 mt-2 w-64 bg-white rounded-xl
                shadow-2xl border-2 border-gray-100 p-2 z-50">
        <a href="/profile"
           class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50
                  text-gray-900 text-sm transition-all font-medium">
            <i class="fas fa-user w-5 text-center text-gray-500"></i>
            <span>My Profile</span>
        </a>
        <a href="/settings"
           class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50
                  text-gray-900 text-sm transition-all font-medium">
            <i class="fas fa-cog w-5 text-center text-gray-500"></i>
            <span>Settings</span>
        </a>
        <hr class="my-2 border-gray-200">
        <button onclick="logout()"
            class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-50
                   text-red-600 text-sm w-full text-left transition-all font-medium">
            <i class="fas fa-sign-out-alt w-5 text-center"></i>
            <span>Sign Out</span>
        </button>
    </div>
</div>

<script>
function toggleUserDropdown() {
    document.getElementById('userDropdown').classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    // Close dropdown when clicking outside
    const wrapper = document.querySelector('[onclick="toggleUserDropdown()"]')?.parentElement;
    if (wrapper && !wrapper.contains(e.target)) {
        document.getElementById('userDropdown')?.classList.add('hidden');
    }
});
</script>
```

### 1.2 Sidebar Toggle JS

```javascript
var isCollapsed = false;

function toggleSidebar() {
    var sidebar      = document.getElementById('sidebar');
    var mainContent  = document.getElementById('mainContent');
    var navTexts     = document.querySelectorAll('.nav-text');
    var logoExpanded = document.querySelector('.logo-expanded');
    var logoCollapsed= document.querySelector('.logo-collapsed');

    isCollapsed = !isCollapsed;

    if (isCollapsed) {
        sidebar.classList.replace('w-64', 'w-20');
        mainContent.classList.replace('ml-64', 'ml-20');
        navTexts.forEach(t => t.classList.add('hidden'));
        logoExpanded.classList.add('hidden');
        logoCollapsed.classList.remove('hidden');
        document.querySelectorAll('.nav-link').forEach(l => {
            l.classList.add('justify-center');
            l.classList.remove('gap-3');
        });
    } else {
        sidebar.classList.replace('w-20', 'w-64');
        mainContent.classList.replace('ml-20', 'ml-64');
        navTexts.forEach(t => t.classList.remove('hidden'));
        logoExpanded.classList.remove('hidden');
        logoCollapsed.classList.add('hidden');
        document.querySelectorAll('.nav-link').forEach(l => {
            l.classList.remove('justify-center');
            l.classList.add('gap-3');
        });
    }
}
```

---

## 2. CSS Custom Properties

Semua warna dan ukuran menggunakan CSS custom properties yang di-set dari preferences user.

### Deklarasi di `<head>`

```html
<style>
    :root {
        --primary-color: #c62828;          /* warna utama (hex) */
        --primary-rgb: 198, 40, 40;        /* RGB components untuk rgba() */
        --primary-dark-rgb: 183, 28, 28;   /* RGB components warna lebih gelap */
        --font-size-base: 14px;            /* 12px=small, 14px=medium, 16px=large */
        --bg-color: #f9fafb;               /* background halaman */
        --text-color: #111827;             /* warna teks utama */
        --card-bg: #ffffff;                /* background card / header */
    }

    body {
        font-size: var(--font-size-base);
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
    }

    .sidebar-transition {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Helper classes (gunakan di seluruh halaman) */
    .primary-bg      { background-color: var(--primary-color) !important; }
    .primary-text    { color: var(--primary-color) !important; }
    .primary-border  { border-color: var(--primary-color) !important; }
    .primary-gradient {
        background: linear-gradient(135deg,
            rgb(var(--primary-dark-rgb)),
            rgb(var(--primary-rgb))) !important;
    }

    /* Dark mode overrides (aktif saat theme=dark) */
    .dark-mode .bg-white   { background-color: #1f2937 !important; }
    .dark-mode .bg-gray-50 { background-color: #111827 !important; }
    /* ... dst */
</style>
```

### Sidebar Styles (gradient vs solid)

EcoSystem memiliki dua opsi `sidebar_style` (preferences):

```css
/* gradient (default) */
.sidebar-gradient {
    background: linear-gradient(180deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb)));
}

/* solid */
.sidebar-solid {
    background-color: rgb(var(--primary-rgb));
}
```

### Mapping Preferences → CSS Values

| Preference | Nilai | CSS Variable |
|---|---|---|
| `font_size: small` | `12px` | `--font-size-base` |
| `font_size: medium` | `14px` | `--font-size-base` |
| `font_size: large` | `16px` | `--font-size-base` |
| `theme: light` | `#f9fafb` / `#111827` / `#fff` | `--bg-color` / `--text-color` / `--card-bg` |
| `theme: dark` | `#111827` / `#f9fafb` / `#1f2937` | sama |
| `primary_color: #c62828` | `198, 40, 40` | `--primary-rgb` |

### Helper: Hex → RGB

```javascript
function hexToRgb(hex) {
    const r = parseInt(hex.slice(1,3), 16);
    const g = parseInt(hex.slice(3,5), 16);
    const b = parseInt(hex.slice(5,7), 16);
    return `${r}, ${g}, ${b}`;
}

function darkenRgb(hex, amount = 15) {
    const r = Math.max(0, parseInt(hex.slice(1,3), 16) - amount);
    const g = Math.max(0, parseInt(hex.slice(3,5), 16) - amount);
    const b = Math.max(0, parseInt(hex.slice(5,7), 16) - amount);
    return `${r}, ${g}, ${b}`;
}

// Apply ke DOM saat preferensi dimuat
function applyTheme(prefs) {
    const root = document.documentElement;
    root.style.setProperty('--primary-color',    prefs.primary_color);
    root.style.setProperty('--primary-rgb',      hexToRgb(prefs.primary_color));
    root.style.setProperty('--primary-dark-rgb', darkenRgb(prefs.primary_color));

    const isDark = prefs.theme === 'dark';
    root.style.setProperty('--bg-color',   isDark ? '#111827' : '#f9fafb');
    root.style.setProperty('--text-color', isDark ? '#f9fafb' : '#111827');
    root.style.setProperty('--card-bg',    isDark ? '#1f2937' : '#ffffff');

    const fontMap = { small: '12px', medium: '14px', large: '16px' };
    root.style.setProperty('--font-size-base', fontMap[prefs.font_size] || '14px');
}
```

---

## 3. Toast Notification System

Sistem toast sudah didokumentasikan lengkap di `docs/jarvies-toast-guide.md`.

**Ringkasan:** 4 tipe (`success`, `error`, `warning`, `info`), posisi fixed top-right, auto-dismiss dengan progress bar.

```javascript
// Contoh penggunaan
showToast('success', 'Berhasil', 'Tiket berhasil dikirim.');
showToast('error', 'Gagal', 'Terjadi kesalahan. Coba lagi.');
showToast('info', 'Info', 'Tiket Anda sedang diproses.');
```

> Lihat file `docs/jarvies-toast-guide.md` untuk HTML, CSS, dan JS lengkap.

---

## 4. Notification Bell

### 4.1 HTML

Tempatkan di dalam header, di sebelah kiri User Menu:

```html
<!-- Di dalam header, sebelum User Menu -->
<div class="relative" id="bellWrapper">
    <button id="bellBtn" onclick="toggleBellDropdown()"
        class="relative w-10 h-10 flex items-center justify-center border-2
               border-gray-200 rounded-xl hover:border-red-800 hover:bg-red-50
               transition-all text-gray-600 hover:text-red-800">
        <i class="fas fa-bell"></i>
        <!-- Badge unread count -->
        <span id="bellBadge"
              class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1
                     bg-red-600 rounded-full border-2 border-white text-white
                     text-[10px] font-bold flex items-center justify-center leading-none">
        </span>
    </button>

    <!-- Dropdown -->
    <div id="bellDropdown"
         class="hidden absolute top-full right-0 mt-2 w-80 bg-white rounded-xl
                shadow-2xl border border-gray-100 z-50 overflow-hidden">
        <!-- Header dropdown -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <span class="text-sm font-semibold text-gray-800">Notifications</span>
            <div class="flex gap-2">
                <button onclick="markAllNotificationsRead()"
                    class="text-xs text-red-700 hover:underline font-medium">
                    Mark all read
                </button>
                <a href="/notifications" class="text-xs text-gray-500 hover:underline">
                    View all
                </a>
            </div>
        </div>
        <!-- List notifikasi -->
        <div id="bellNotifList" class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <div class="px-4 py-6 text-center text-xs text-gray-400">Loading...</div>
        </div>
    </div>
</div>
```

### 4.2 CSS (jika tidak menggunakan Tailwind)

```css
#bellDropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 320px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    border: 1px solid #f3f4f6;
    z-index: 50;
    overflow: hidden;
}

#bellBadge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    background: #dc2626;
    border-radius: 999px;
    border: 2px solid white;
    color: white;
    font-size: 10px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

#bellNotifList {
    max-height: 320px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    gap: 12px;
    padding: 12px 16px;
    transition: background 0.15s;
    border-bottom: 1px solid #f9fafb;
    text-decoration: none;
    color: inherit;
}
.notif-item:hover      { background: #f9fafb; }
.notif-item.unread     { background: #fef2f2; }
.notif-item.unread:hover { background: #fee2e2; }

.notif-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #fee2e2;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
}

.notif-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #ef4444;
    flex-shrink: 0;
    margin-top: 6px;
}
```

### 4.3 JavaScript (EcoSystem — basis implementasi)

```javascript
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

    // Tutup saat klik di luar
    document.addEventListener('click', function (e) {
        if (!document.getElementById('bellWrapper')?.contains(e.target)) {
            document.getElementById('bellDropdown')?.classList.add('hidden');
            bellOpen = false;
        }
    });

    // Polling unread count setiap 30 detik
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

    // Load isi dropdown
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
                    const ticketUrl = n.ticket_id ? '/ticket/' + n.ticket_id : '/notifications';
                    return `<a href="${ticketUrl}" onclick="markNotifRead(${n.id}, event)"
                        class="notif-item ${isUnread ? 'unread' : ''}">
                        <div class="notif-avatar">
                            <i class="fas fa-at text-red-700" style="font-size:12px;"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:12px;font-weight:600;color:#1f2937;
                                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                ${escapeHtml(n.from_name || 'Someone')}
                            </p>
                            <p style="font-size:12px;color:#6b7280;margin-top:2px;
                                      display:-webkit-box;-webkit-line-clamp:2;
                                      -webkit-box-orient:vertical;overflow:hidden;">
                                ${escapeHtml(n.preview || '')}
                            </p>
                            <p style="font-size:10px;color:#9ca3af;margin-top:4px;">
                                ${escapeHtml(n.created_at || '')}
                            </p>
                        </div>
                        ${isUnread ? '<span class="notif-dot"></span>' : ''}
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
        // biarkan link navigasi berjalan
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

    // Expose global
    window.toggleBellDropdown       = toggleBellDropdown;
    window.markAllNotificationsRead = markAllNotificationsRead;
    window.markNotifRead            = markNotifRead;

    // Mulai polling
    fetchUnreadCount();
    setInterval(fetchUnreadCount, 30000);
})();
```

### 4.4 API Endpoints Notifikasi (EcoSystem)

Semua endpoint membutuhkan session cookie (login EcoSystem).

| Method | Endpoint | Deskripsi |
|---|---|---|
| `GET` | `/api/notifications` | List 20 notif terbaru + `unread_count` |
| `GET` | `/api/notifications/unread-count` | Hanya jumlah unread (ringan, untuk polling) |
| `PUT` | `/api/notifications/{id}/read` | Tandai satu notif sebagai baca |
| `PUT` | `/api/notifications/read-all` | Tandai semua notif sebagai baca |
| `DELETE` | `/api/notifications/bulk-delete` | Hapus semua notif yang sudah dibaca |

**Response `GET /api/notifications`:**
```json
{
    "success": true,
    "unread_count": 3,
    "data": [
        {
            "id": 45,
            "type": "new_message",
            "ticket_id": 101,
            "message_id": 202,
            "from_name": "Budi Santoso",
            "preview": "Masalah sudah kami cek...",
            "is_read": false,
            "created_at": "2 minutes ago"
        }
    ]
}
```

**Response `GET /api/notifications/unread-count`:**
```json
{
    "success": true,
    "count": 3
}
```

---

## 5. Settings Menu

### 5.1 Struktur Tab

Settings page menggunakan tab-based navigation dengan 6 tab:

| Tab | ID | Isi |
|---|---|---|
| Appearance | `tab-appearance` | Theme (light/dark/auto), Primary Color, Sidebar Style |
| Preferences | `tab-preferences` | Font Size, Compact Mode, Animations |
| Notifications | `tab-notifications` | Enable notifications, Email notifications, Push notifications |
| Security | `tab-security` | Ganti password |
| Language & Region | `tab-language` | Bahasa, Timezone, Format tanggal |
| About | `tab-about` | Versi app, informasi sistem |

### 5.2 HTML Skeleton Settings Page

```html
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage your workspace preferences</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="resetSettings()"
                class="px-4 py-2 bg-white border border-gray-300 text-gray-700
                       text-sm font-medium rounded-lg hover:bg-gray-50 transition-all">
                Reset to Default
            </button>
            <button onclick="saveSettings()"
                class="px-5 py-2 text-white text-sm font-medium rounded-lg transition-all"
                style="background-color: var(--primary-color);">
                Save Changes
            </button>
        </div>
    </div>

    <!-- Tab Container -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px overflow-x-auto">
                <button onclick="switchTab('appearance')" data-tab="appearance"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2
                           whitespace-nowrap transition-all"
                    style="border-color: var(--primary-color); color: var(--primary-color);">
                    Appearance
                </button>
                <button onclick="switchTab('preferences')" data-tab="preferences"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2
                           border-transparent text-gray-500 whitespace-nowrap hover:text-gray-800 transition-all">
                    Preferences
                </button>
                <button onclick="switchTab('notifications')" data-tab="notifications"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2
                           border-transparent text-gray-500 whitespace-nowrap hover:text-gray-800 transition-all">
                    Notifications
                </button>
                <button onclick="switchTab('security')" data-tab="security"
                    class="settings-tab px-6 py-4 text-sm font-semibold border-b-2
                           border-transparent text-gray-500 whitespace-nowrap hover:text-gray-800 transition-all">
                    Security
                </button>
            </nav>
        </div>

        <!-- Tab Contents -->
        <div id="tab-appearance" class="settings-content p-6">
            <!-- Theme Mode -->
            <div class="mb-8">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">Theme Mode</h3>
                <div class="grid grid-cols-3 gap-4 max-w-xl mt-4">
                    <div onclick="selectTheme('light')" class="theme-option cursor-pointer border-2 rounded-xl p-5 text-center transition-all hover:border-red-800" id="theme-light">
                        <div class="text-sm font-semibold text-gray-900">Light</div>
                        <div class="text-xs text-gray-400">Bright & clean</div>
                    </div>
                    <div onclick="selectTheme('dark')" class="theme-option cursor-pointer border-2 rounded-xl p-5 text-center transition-all hover:border-red-800" id="theme-dark">
                        <div class="text-sm font-semibold text-gray-900">Dark</div>
                        <div class="text-xs text-gray-400">Easy on eyes</div>
                    </div>
                    <div onclick="selectTheme('auto')" class="theme-option cursor-pointer border-2 rounded-xl p-5 text-center transition-all hover:border-red-800" id="theme-auto">
                        <div class="text-sm font-semibold text-gray-900">System</div>
                        <div class="text-xs text-gray-400">Follow device</div>
                    </div>
                </div>
            </div>

            <!-- Primary Color -->
            <div class="mb-8">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-1">Primary Color</h3>
                <div class="flex items-center gap-4 mt-4">
                    <input type="color" id="primaryColorPicker"
                        class="w-12 h-12 rounded-lg cursor-pointer border border-gray-200"
                        value="#c62828" onchange="previewColor(this.value)">
                    <span class="text-sm text-gray-600">Choose accent color</span>
                </div>
            </div>
        </div>

        <div id="tab-preferences" class="settings-content p-6 hidden">
            <!-- Font Size -->
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Font Size</h3>
                <select id="fontSizeSelect" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="small">Small (12px)</option>
                    <option value="medium" selected>Medium (14px)</option>
                    <option value="large">Large (16px)</option>
                </select>
            </div>

            <!-- Compact Mode -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Compact Mode</h3>
                    <p class="text-xs text-gray-400">Reduce spacing for denser layout</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="compactModeToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:bg-red-700
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all">
                    </div>
                </label>
            </div>

            <!-- Animations -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Show Animations</h3>
                    <p class="text-xs text-gray-400">Enable smooth transitions</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="showAnimationsToggle" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 rounded-full peer
                                peer-checked:after:translate-x-full peer-checked:bg-red-700
                                after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all">
                    </div>
                </label>
            </div>
        </div>

        <div id="tab-notifications" class="settings-content p-6 hidden">
            <div class="space-y-4">
                <!-- Toggle: notifications_enabled -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Enable Notifications</h3>
                        <p class="text-xs text-gray-400">Show in-app notification bell</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="notifEnabled" class="sr-only peer" checked>
                        <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-red-700 ..."></div>
                    </label>
                </div>
            </div>
        </div>

        <div id="tab-security" class="settings-content p-6 hidden">
            <!-- Ganti Password -->
            <div class="max-w-md space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" id="currentPassword"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" id="newPassword"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" id="confirmPassword"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <button onclick="changePassword()"
                    class="px-5 py-2 text-white text-sm font-medium rounded-lg"
                    style="background-color: var(--primary-color);">
                    Update Password
                </button>
            </div>
        </div>
    </div>
</div>
```

### 5.3 JavaScript Settings

```javascript
// ── State: current preferences ──
let currentPrefs = {
    theme:                 'light',
    primary_color:         '#c62828',
    sidebar_style:         'gradient',
    font_size:             'medium',
    compact_mode:          false,
    show_animations:       true,
    notifications_enabled: true,
    email_notifications:   true,
    push_notifications:    false,
};

// ── Tab Switcher ──
function switchTab(tabName) {
    // Hide all tab content panels
    document.querySelectorAll('.settings-content').forEach(el => el.classList.add('hidden'));
    // Reset all tab buttons
    document.querySelectorAll('.settings-tab').forEach(btn => {
        btn.style.borderColor = 'transparent';
        btn.style.color = '#6b7280';
    });
    // Show selected panel
    document.getElementById('tab-' + tabName)?.classList.remove('hidden');
    // Activate selected tab button
    const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.style.borderColor = getComputedStyle(document.documentElement)
                                        .getPropertyValue('--primary-color');
        activeBtn.style.color = getComputedStyle(document.documentElement)
                                  .getPropertyValue('--primary-color');
    }
}

// ── Theme Selection ──
function selectTheme(theme) {
    currentPrefs.theme = theme;
    document.querySelectorAll('.theme-option').forEach(el => {
        el.style.borderColor = '#e5e7eb';
        el.style.backgroundColor = '';
    });
    const selected = document.getElementById('theme-' + theme);
    if (selected) {
        selected.style.borderColor = currentPrefs.primary_color;
        selected.style.backgroundColor = '#fef2f2';
    }
}

// ── Color Preview (live) ──
function previewColor(hex) {
    currentPrefs.primary_color = hex;
    document.documentElement.style.setProperty('--primary-color', hex);
    document.documentElement.style.setProperty('--primary-rgb', hexToRgb(hex));
    document.documentElement.style.setProperty('--primary-dark-rgb', darkenRgb(hex));
}

// ── Collect form values → build preferences object ──
function collectPreferences() {
    return {
        theme:                 currentPrefs.theme,
        primary_color:         document.getElementById('primaryColorPicker')?.value || currentPrefs.primary_color,
        font_size:             document.getElementById('fontSizeSelect')?.value || 'medium',
        compact_mode:          document.getElementById('compactModeToggle')?.checked || false,
        show_animations:       document.getElementById('showAnimationsToggle')?.checked ?? true,
        notifications_enabled: document.getElementById('notifEnabled')?.checked ?? true,
        email_notifications:   document.getElementById('emailNotifToggle')?.checked ?? true,
        push_notifications:    document.getElementById('pushNotifToggle')?.checked || false,
    };
}

// ── Save to server ──
function saveSettings() {
    const prefs = collectPreferences();
    fetch('/api/settings/preferences', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify(prefs),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Saved', data.message || 'Settings saved successfully!');
            applyTheme(prefs); // terapkan langsung ke UI
        } else {
            showToast('error', 'Failed', data.message || 'Failed to save settings.');
        }
    })
    .catch(() => showToast('error', 'Error', 'Network error. Try again.'));
}

// ── Reset to default ──
function resetSettings() {
    fetch('/api/settings/reset', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Reset', 'Settings reset to default.');
            applyTheme(data.preferences); // terapkan default ke UI
            // Reload halaman agar form inputs juga reset
            setTimeout(() => location.reload(), 1000);
        }
    });
}
```

### 5.4 API Endpoints Settings (EcoSystem)

| Method | Endpoint | Deskripsi |
|---|---|---|
| `POST` | `/api/settings/preferences` | Simpan preferences (JSON body) |
| `POST` | `/api/settings/reset` | Reset ke default |

**Request Body `POST /api/settings/preferences`:**
```json
{
    "theme": "light",
    "primary_color": "#c62828",
    "sidebar_style": "gradient",
    "font_size": "medium",
    "compact_mode": false,
    "show_animations": true,
    "notifications_enabled": true,
    "email_notifications": true,
    "push_notifications": false
}
```

**Response:**
```json
{
    "success": true,
    "message": "Settings saved successfully!",
    "preferences": { /* preferences yang disimpan */ }
}
```

> **Catatan EcoSystem:** Preferences saat ini disimpan di Laravel session (`session('user_preferences')`).
> Untuk Jarvies, simpan di `localStorage` atau database tabel `customer_preferences`.

---

## 6. Adaptasi Jarvies

### 6.1 Perbedaan Notifikasi

| Aspek | EcoSystem (Employee) | Jarvies (Customer) |
|---|---|---|
| Trigger | Agent reply / mention | Ticket mendapat update baru |
| Sumber data | Tabel `notifications` | Polling `/api/tickets` atau endpoint baru |
| Auth | Session cookie | Bearer token |
| Endpoint | `/api/notifications/unread-count` | Buat endpoint baru (lihat 6.2) |

### 6.2 Polling Update Tiket untuk Jarvies

Karena Jarvies tidak punya tabel `notifications`, polling dilakukan via endpoint tiket.

#### Opsi A — Polling `/api/tickets` (Mudah, tanpa perubahan backend)

```javascript
let knownTicketStates = {}; // { ticketId: { updated_at, message_count } }

async function pollTicketUpdates() {
    try {
        const res = await fetch(`${BASE_URL}/api/tickets`, {
            headers: { Authorization: `Bearer ${accessToken}` }
        });
        const data = await res.json();
        if (!data.success) return;

        data.data.forEach(ticket => {
            const prev = knownTicketStates[ticket.ticket_id];
            const curr = ticket.updated_at;

            if (prev && prev !== curr) {
                // Ada update baru pada tiket ini
                showToast('info', 'Ticket Updated',
                    `Tiket #${ticket.ticket_number} mendapat pembaruan.`);
                showBellBadge();
            }

            knownTicketStates[ticket.ticket_id] = curr;
        });

    } catch (e) {
        console.warn('Polling error:', e);
    }
}

// Inisialisasi: isi knownTicketStates tanpa notif, lalu mulai polling
async function initTicketPolling() {
    const res  = await fetch(`${BASE_URL}/api/tickets`, {
        headers: { Authorization: `Bearer ${accessToken}` }
    });
    const data = await res.json();
    if (data.success) {
        data.data.forEach(t => { knownTicketStates[t.ticket_id] = t.updated_at; });
    }
    setInterval(pollTicketUpdates, 30000); // polling setiap 30 detik
}

initTicketPolling();
```

#### Opsi B — Endpoint Khusus Notifikasi Customer (Backend EcoSystem)

Tambahkan route di EcoSystem untuk customer Jarvies:

```
GET /api/customer/notifications/unread-count
Authorization: Bearer {jarvies_token}
```

Backend EcoSystem cukup menghitung pesan baru sejak `last_seen_at` customer:

```php
// Di routes/api.php
Route::middleware('jarvies.auth')->group(function () {
    Route::get('/customer/notifications/unread-count', function (Request $req) {
        $customerId = $req->attributes->get('customer_id');
        $lastSeen   = $req->query('last_seen'); // ISO timestamp

        $count = DB::table('ticket_message')
            ->join('tickets', 'tickets.id', '=', 'ticket_message.ticket_id')
            ->where('tickets.customer_id', $customerId)
            ->where('ticket_message.sender_type', '!=', 'customer')
            ->where('ticket_message.created_at', '>', $lastSeen ?? now()->subDays(7))
            ->count();

        return response()->json(['success' => true, 'count' => $count]);
    });
});
```

### 6.3 Bell HTML untuk Jarvies

Gunakan HTML yang sama dari [Section 4.1](#41-html), dengan sedikit adaptasi:

```javascript
// Ganti fetch URL dari EcoSystem session ke Jarvies token
function fetchUnreadCount() {
    const lastSeen = localStorage.getItem('notif_last_seen') || '';
    fetch(`${ECOSYSTEM_BASE_URL}/api/customer/notifications/unread-count?last_seen=${lastSeen}`, {
        headers: { Authorization: `Bearer ${accessToken}` }
    })
    .then(r => r.json())
    .then(data => {
        const badge = document.getElementById('bellBadge');
        if (!badge) return;
        const count = data.count || 0;
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.toggle('hidden', count === 0);
    })
    .catch(() => {});
}

// Saat dropdown bell dibuka, tandai semua sebagai "sudah dilihat"
function onBellOpen() {
    localStorage.setItem('notif_last_seen', new Date().toISOString());
    document.getElementById('bellBadge')?.classList.add('hidden');
}
```

### 6.4 Settings di Jarvies

Jarvies menyimpan preferences di `localStorage` (tidak perlu server):

```javascript
const DEFAULT_PREFS = {
    theme: 'light',
    primary_color: '#c62828',  // warna Jarvies bisa berbeda
    font_size: 'medium',
    compact_mode: false,
    show_animations: true,
    notifications_enabled: true,
};

function loadPreferences() {
    const saved = localStorage.getItem('jarvies_preferences');
    return saved ? { ...DEFAULT_PREFS, ...JSON.parse(saved) } : { ...DEFAULT_PREFS };
}

function savePreferences(prefs) {
    localStorage.setItem('jarvies_preferences', JSON.stringify(prefs));
    applyTheme(prefs);
    showToast('success', 'Saved', 'Settings saved!');
}

function resetPreferences() {
    localStorage.removeItem('jarvies_preferences');
    applyTheme(DEFAULT_PREFS);
    showToast('success', 'Reset', 'Settings reset to default.');
    setTimeout(() => location.reload(), 800);
}

// Panggil saat app pertama load
const prefs = loadPreferences();
applyTheme(prefs);
```

### 6.5 Struktur Sidebar Jarvies

Jarvies memiliki menu yang lebih sederhana dari EcoSystem:

```html
<nav class="px-4 py-6 space-y-1">
    <a href="/dashboard" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl
        text-white/80 hover:text-white hover:bg-white/15 transition-all text-sm font-medium">
        <i class="fas fa-home w-5 text-center flex-shrink-0"></i>
        <span class="nav-text">Dashboard</span>
    </a>
    <a href="/tickets" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl
        text-white/80 hover:text-white hover:bg-white/15 transition-all text-sm font-medium">
        <i class="fas fa-ticket-alt w-5 text-center flex-shrink-0"></i>
        <span class="nav-text">My Tickets</span>
    </a>
    <a href="/tickets/new" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl
        text-white/80 hover:text-white hover:bg-white/15 transition-all text-sm font-medium">
        <i class="fas fa-plus-circle w-5 text-center flex-shrink-0"></i>
        <span class="nav-text">Submit Ticket</span>
    </a>
    <a href="/settings" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl
        text-white/80 hover:text-white hover:bg-white/15 transition-all text-sm font-medium">
        <i class="fas fa-cog w-5 text-center flex-shrink-0"></i>
        <span class="nav-text">Settings</span>
    </a>
</nav>
```

### 6.6 Warna Default Jarvies

Untuk konsistensi visual, gunakan warna dasar yang sama atau sedikit berbeda:

```javascript
// EcoSystem default
const ECOSYSTEM_PRIMARY = '#c62828'; // merah gelap

// Jarvies bisa menggunakan warna yang sama atau brand sendiri
const JARVIES_PRIMARY = '#c62828'; // sama agar konsisten
// atau
const JARVIES_PRIMARY = '#1565c0'; // biru jika Jarvies punya brand sendiri
```

---

## 7. Checklist Implementasi Jarvies

### Layout & Theming
- [ ] CSS custom properties (`--primary-color`, `--primary-rgb`, `--bg-color`, `--text-color`, `--card-bg`) dideklarasikan di `:root`
- [ ] Fungsi `applyTheme(prefs)` diimplementasikan
- [ ] Preferences disimpan di `localStorage` dengan key `jarvies_preferences`
- [ ] `applyTheme()` dipanggil saat app pertama load
- [ ] Sidebar dengan `toggleSidebar()` (collapsed ↔ expanded)
- [ ] Header sticky dengan hamburger, title, bell, user menu
- [ ] `.sidebar-transition` untuk animasi sidebar

### Toast
- [ ] HTML `#toast-container` ada di layout utama
- [ ] CSS toast (4 tipe) ada di `<style>`
- [ ] Fungsi `showToast(type, title, message)` tersedia global
- [ ] Lihat `docs/jarvies-toast-guide.md` untuk detail lengkap

### Notification Bell
- [ ] HTML bell dengan `id="bellWrapper"`, `id="bellBtn"`, `id="bellBadge"`, `id="bellDropdown"`, `id="bellNotifList"`
- [ ] Polling `fetchUnreadCount()` setiap 30 detik
- [ ] `toggleBellDropdown()` membuka/menutup dropdown
- [ ] `loadBellNotifications()` fetch data dan render notifikasi
- [ ] Notifikasi ditandai read saat diklik (`markNotifRead()`)
- [ ] `markAllNotificationsRead()` tersedia di header dropdown
- [ ] Klik di luar dropdown → tutup otomatis

### Settings Menu
- [ ] Tab navigation dengan `switchTab(tabName)`
- [ ] Tab Appearance: theme selector (light/dark), color picker
- [ ] Tab Preferences: font size, compact mode, animations
- [ ] Tab Notifications: enable/disable notifikasi
- [ ] Tab Security: ganti password
- [ ] Tombol Save Changes memanggil `saveSettings()` → `savePreferences()`
- [ ] Tombol Reset to Default memanggil `resetSettings()` → `resetPreferences()`
- [ ] Perubahan warna primary diterapkan langsung via `previewColor()`

### Notifikasi Update Tiket
- [ ] Pilih Opsi A (polling `/api/tickets`) atau Opsi B (endpoint custom EcoSystem)
- [ ] `initTicketPolling()` dipanggil setelah login
- [ ] Saat ada update: `showToast('info', ...)` + update badge bell
- [ ] `last_seen` disimpan ke `localStorage` saat bell dibuka

---

## Catatan Penting

1. **CSRF Token** — EcoSystem menggunakan CSRF token untuk semua `PUT`/`POST`/`DELETE`. Tambahkan `<meta name="csrf-token" content="...">` di `<head>`. Jarvies menggunakan Bearer token, sehingga CSRF umumnya tidak diperlukan jika API stateless.

2. **FontAwesome** — Icon diambil dari FontAwesome 6. Pastikan CDN FA sudah di-include:
   ```html
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   ```

3. **Tailwind CSS** — EcoSystem menggunakan Tailwind via CDN. Jarvies bisa menggunakan Tailwind yang sama, atau adaptasi CSS vanilla menggunakan helper classes yang sudah dideskripsikan di atas.

4. **Dark mode** — EcoSystem mengimplementasikan dark mode via override class Tailwind di `<style>` tag. Jarvies bisa menggunakan pendekatan yang sama atau `prefers-color-scheme` media query.

5. **Polling interval** — 30 detik cukup untuk notifikasi tiket. Jangan terlalu sering (< 10 detik) agar tidak membebani server.

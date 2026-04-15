# EcoSystem-2 — Style System Documentation

> Panduan lengkap warna, tipografi, komponen UI, dan notifikasi untuk EcoSystem-2.

---

## Daftar Isi

1. [Color Palette](#1-color-palette)
2. [CSS Custom Properties (Dynamic Theme)](#2-css-custom-properties-dynamic-theme)
3. [Tipografi](#3-tipografi)
4. [Spacing & Border Radius](#4-spacing--border-radius)
5. [Button](#5-button)
6. [Card](#6-card)
7. [Form](#7-form)
8. [Badge & Status Tag](#8-badge--status-tag)
9. [Toast / Notifikasi](#9-toast--notifikasi)
10. [Modal](#10-modal)
11. [Sidebar & Navigasi](#11-sidebar--navigasi)
12. [Shadow & Transisi](#12-shadow--transisi)
13. [Warna Prioritas & Kalender](#13-warna-prioritas--kalender)

---

## 1. Color Palette

### Brand / Primary
| Token | Hex | Keterangan |
|-------|-----|------------|
| Primary | `#991B1B` | Warna utama aplikasi (dark red) |
| Primary Hover | `#8E2026` | Hover state tombol utama |
| Primary Light | `#A7262D` | Varian login page |
| Primary Gradient | `linear-gradient(135deg, rgb(dark), rgb(primary))` | Sidebar & tombol gradient |

### Grayscale
| Token Tailwind | Keterangan |
|----------------|------------|
| `text-gray-900` | Teks utama |
| `text-gray-800` | Teks sekunder |
| `text-gray-700` | Label |
| `text-gray-600` | Meta / keterangan |
| `text-gray-500` | Placeholder |
| `text-gray-400` | Disabled |
| `bg-gray-50` | Background terang |
| `bg-gray-100` | Background sekunder |
| `bg-gray-200` | Border default |
| `bg-gray-300` | Divider |

### Status Colors
| Warna | Tailwind bg | Tailwind text | Keterangan |
|-------|-------------|---------------|------------|
| Hijau | `bg-green-500` | `text-green-700` | Sukses, Completed |
| Merah | `bg-red-500` | `text-red-700` | Error, Danger, High priority |
| Biru | `bg-blue-500` | `text-blue-700` | Info, Low priority, Open |
| Kuning | `bg-yellow-500` | `text-yellow-700` | Warning, Medium priority |
| Ungu | `bg-purple-500` | `text-purple-700` | Deadline, In Progress |
| Oranye | `bg-orange-500` | `text-orange-700` | Action required |

### Light Status Backgrounds (untuk badge)
```
bg-red-50 / bg-red-100
bg-green-50 / bg-green-100
bg-blue-50 / bg-blue-100
bg-yellow-50 / bg-yellow-100
bg-purple-50 / bg-purple-100
bg-orange-50 / bg-orange-100
```

---

## 2. CSS Custom Properties (Dynamic Theme)

Didefinisikan di [resources/views/dashboard.blade.php](../resources/views/dashboard.blade.php) dan di-inject via Blade (dapat diubah melalui preferensi user).

```css
:root {
    --primary-color: #991b1b;
    --primary-rgb: 153, 27, 27;
    --primary-dark-rgb: 142, 32, 38;
    --font-size-base: 14px;
    --bg-color: #f9fafb;
    --text-color: #111827;
    --card-bg: #ffffff;
}
```

### Utility Classes yang Bergantung pada Variabel
| Class | CSS yang dihasilkan |
|-------|---------------------|
| `.primary-bg` | `background-color: var(--primary-color)` |
| `.primary-text` | `color: var(--primary-color)` |
| `.primary-border` | `border-color: var(--primary-color)` |
| `.primary-gradient` | `background: linear-gradient(135deg, rgb(var(--primary-dark-rgb)), rgb(var(--primary-rgb)))` |
| `.primary-solid` | `background-color: rgb(var(--primary-rgb))` |

---

## 3. Tipografi

### Font Families
| Font | Digunakan di |
|------|--------------|
| **Instrument Sans** | Default global (`@theme` di app.css) |
| **Inter** | Dashboard & halaman auth (via Google Fonts) |
| **Poppins** | Login page (via login.css) |

File CSS: [resources/css/app.css](../resources/css/app.css), [resources/css/login.css](../resources/css/login.css)

### Font Size
| Class Tailwind | rem | Keterangan |
|----------------|-----|------------|
| `text-4xl` | 2.25rem | Heading utama |
| `text-3xl` | 1.875rem | Heading besar |
| `text-2xl` | 1.5rem | Heading medium |
| `text-xl` | 1.25rem | Judul section |
| `text-lg` | 1.125rem | Sub-heading |
| `text-base` | 1rem | Body text |
| `text-sm` | 0.875rem | Teks sekunder |
| `text-xs` | 0.75rem | Caption, label |
| `text-[11px]` | 0.6875rem | Label kecil (custom) |
| `text-[13px]` | 0.8125rem | Custom ukuran |

### Font Weight
| Class | Weight | Keterangan |
|-------|--------|------------|
| `font-bold` | 700 | Header |
| `font-semibold` | 600 | Title section |
| `font-medium` | 500 | Teks teraksentuasi |
| `font-normal` | 400 | Body text |
| `font-light` | 300 | Teks de-emphasized |

---

## 4. Spacing & Border Radius

### Padding Standar
| Konteks | Class |
|---------|-------|
| Tombol kecil | `px-3 py-1.5` |
| Tombol standar | `px-4 py-2` |
| Card | `p-4` hingga `p-6` |
| Section | `py-6 px-4` hingga `py-8 px-8` |

### Gap
| Ukuran | Class |
|--------|-------|
| Ketat | `gap-1` — `gap-2` |
| Standar | `gap-3` — `gap-4` |
| Longgar | `gap-5` — `gap-6` |

### Border Radius
| Class | rem | Digunakan untuk |
|-------|-----|-----------------|
| `rounded-md` | 0.375rem | Tombol kecil, input |
| `rounded-lg` | 0.5rem | Tombol standar, card kecil |
| `rounded-xl` | 0.75rem | Card, panel |
| `rounded-2xl` | 1rem | Card besar |
| `rounded-3xl` | 1.5rem | Container utama |
| `rounded-full` | 50% | Avatar, badge bulat |

---

## 5. Button

### Primary Button
```html
<button class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
    Label
</button>
```
File: [resources/views/delivery/project/components/primary-button.blade.php](../resources/views/delivery/project/components/primary-button.blade.php)

### Secondary Button
```html
<button class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
    Label
</button>
```
File: [resources/views/delivery/project/components/secondary-button.blade.php](../resources/views/delivery/project/components/secondary-button.blade.php)

### Danger Button
```html
<button class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:ring-2 focus:ring-red-500 transition">
    Hapus
</button>
```
File: [resources/views/delivery/project/components/danger-button.blade.php](../resources/views/delivery/project/components/danger-button.blade.php)

---

## 6. Card

### Card Standar
```html
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition-shadow">
    ...
</div>
```

### Card dengan Aksen Merah (hover)
```html
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:border-red-400 hover:shadow-md transition-all">
    ...
</div>
```

### Card Besar
```html
<div class="bg-white rounded-2xl border border-gray-200 p-6 mb-8 shadow-sm">
    ...
</div>
```

---

## 7. Form

### Input Text
```html
<input class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full">
```

### Input dengan Primary Focus
```css
.input-field:focus {
    border-color: #991b1b;
    box-shadow: 0 0 0 3px rgba(153, 27, 27, 0.1);
}
```

### Label
```html
<label class="block font-medium text-sm text-gray-700">Label</label>
```

### Pesan Error
```html
<p class="text-sm text-red-600">Pesan error</p>
```

File komponen: [resources/views/delivery/project/components/](../resources/views/delivery/project/components/)

---

## 8. Badge & Status Tag

### Template Umum
```html
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }} whitespace-nowrap">
    {{ $label }}
</span>
```

### Mapping Status Tiket
| Status | Class |
|--------|-------|
| `open` | `bg-blue-100 text-blue-700` |
| `in_progress` | `bg-purple-100 text-purple-700` |
| `closed` | `bg-green-100 text-green-700` |
| `wait_to_close` | `bg-orange-100 text-orange-700` |
| `hold` | `bg-gray-100 text-gray-600` |
| `reply` | `bg-yellow-100 text-yellow-700` |
| `cancel` | `bg-red-100 text-red-600` |

### Mapping Status Proyek (PDF Export)
| Status | Background | Text |
|--------|------------|------|
| Not Started | `#f3f4f6` | `#4b5563` |
| In Progress | `#dbeafe` | `#1e40af` |
| Completed | `#d1fae5` | `#065f46` |
| Delayed | `#fee2e2` | `#991b1b` |

File: [resources/views/delivery/project/project-planning/exports/table-pdf.blade.php](../resources/views/delivery/project/project-planning/exports/table-pdf.blade.php)

---

## 9. Toast / Notifikasi

File utama: [resources/views/auth/login.blade.php](../resources/views/auth/login.blade.php) (baris 52–197)

### Container
```css
#toast-container {
    position: fixed;
    top: 1.5rem;
    right: 1.5rem;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-width: 22rem;
    width: 100%;
}
```

### Struktur HTML Toast
```html
<div id="toast-container">
    <div class="toast toast-success show">
        <div class="toast-icon">
            <!-- SVG icon -->
        </div>
        <div class="toast-body">
            <p class="toast-title">Berhasil!</p>
            <p class="toast-message">Data telah disimpan.</p>
        </div>
        <button class="toast-close">&times;</button>
        <div class="toast-progress"></div>
    </div>
</div>
```

### Variasi Toast

#### Success
```css
.toast-success {
    background: #f0fdf4;
    border: 1.5px solid #86efac;
}
.toast-success .toast-icon    { background: #dcfce7; }
.toast-success .toast-icon svg { color: #16a34a; }
.toast-success .toast-title   { color: #14532d; }
.toast-success .toast-message { color: #15803d; }
.toast-success .toast-progress { background: #22c55e; }
```

#### Error
```css
.toast-error {
    background: #fff1f1;
    border: 1.5px solid #fca5a5;
}
.toast-error .toast-icon    { background: #fee2e2; }
.toast-error .toast-icon svg { color: #dc2626; }
.toast-error .toast-title   { color: #991b1b; }
.toast-error .toast-message { color: #b91c1c; }
.toast-error .toast-progress { background: #ef4444; }
```

#### Warning
```css
.toast-warning {
    background: #fffbeb;
    border: 1.5px solid #fcd34d;
}
.toast-warning .toast-icon    { background: #fef9c3; }
.toast-warning .toast-icon svg { color: #d97706; }
.toast-warning .toast-progress { background: #f59e0b; }
```

#### Info
```css
.toast-info {
    background: #eff6ff;
    border: 1.5px solid #93c5fd;
}
.toast-info .toast-icon    { background: #dbeafe; }
.toast-info .toast-icon svg { color: #2563eb; }
.toast-info .toast-progress { background: #3b82f6; }
```

### Animasi Toast
```css
/* State awal (tersembunyi) */
.toast {
    transform: translateX(110%);
    opacity: 0;
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1),
                opacity 0.3s ease;
}

/* State tampil */
.toast.show {
    transform: translateX(0);
    opacity: 1;
}
```

> Toast muncul dari kanan dengan efek spring bounce (`cubic-bezier(0.34, 1.56, 0.64, 1)`).  
> Progress bar berjalan selama durasi tampil, lalu toast otomatis menghilang.

### Ringkasan Warna Toast
| Tipe | Background | Border | Icon bg | Progress bar |
|------|------------|--------|---------|--------------|
| Success | `#f0fdf4` | `#86efac` | `#dcfce7` | `#22c55e` |
| Error | `#fff1f1` | `#fca5a5` | `#fee2e2` | `#ef4444` |
| Warning | `#fffbeb` | `#fcd34d` | `#fef9c3` | `#f59e0b` |
| Info | `#eff6ff` | `#93c5fd` | `#dbeafe` | `#3b82f6` |

---

## 10. Modal

File: [resources/views/delivery/project/components/modal.blade.php](../resources/views/delivery/project/components/modal.blade.php)

### Struktur & Kelas
```html
<!-- Overlay -->
<div class="absolute inset-0 bg-gray-500 opacity-75"></div>

<!-- Dialog -->
<div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:mx-auto">
    ...
</div>
```

### Animasi (Alpine.js x-transition)
```html
x-transition:enter="ease-out duration-300"
x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
x-transition:leave="ease-in duration-200"
x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
```

---

## 11. Sidebar & Navigasi

File: [resources/views/delivery/project/layouts/app.blade.php](../resources/views/delivery/project/layouts/app.blade.php)

### Sidebar
```html
<!-- Sidebar dengan gradient -->
<aside class="primary-gradient text-white shadow-2xl ...">

<!-- Sidebar dengan warna solid -->
<aside class="primary-solid text-white shadow-2xl ...">
```
> Mode dapat dipilih user melalui preferensi (`sidebar_style: 'gradient' | 'solid'`).

### Nav Item
| State | Class |
|-------|-------|
| Aktif | `bg-red-800 text-white shadow-md` |
| Hover | `hover:bg-gray-50 hover:text-gray-900` |
| Default | `text-gray-600` |

### Header
```html
<header class="bg-red-800 shadow-sm sticky top-0 z-20">
```

### Notifikasi Inbox (unread indicator)
```html
<!-- Item unread -->
<div class="px-5 py-4 bg-red-50 hover:bg-gray-50 transition-colors">
    <span class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center">
        <!-- icon -->
    </span>
    <span class="inline-block w-2 h-2 bg-red-500 rounded-full"></span>
</div>

<!-- Item read -->
<div class="px-5 py-4 hover:bg-gray-50 transition-colors">
    <span class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center">
        <!-- icon -->
    </span>
</div>
```

File: [resources/views/notifications/index.blade.php](../resources/views/notifications/index.blade.php)

---

## 12. Shadow & Transisi

### Shadow Scale
| Class | Keterangan |
|-------|------------|
| `shadow-sm` | Card default |
| `shadow-md` | Card hover |
| `shadow-lg` | Dropdown |
| `shadow-xl` | Modal |
| `shadow-2xl` | Sidebar, overlay |

### Transisi
| Class | Keterangan |
|-------|------------|
| `transition-all` | Semua properti |
| `transition-colors` | Perubahan warna |
| `transition-shadow` | Kedalaman shadow |
| `duration-150` | Sangat cepat |
| `duration-200` | Cepat (default tombol) |
| `duration-300` | Sedang (modal, toast) |
| `ease-in-out` | Halus dua arah |
| `ease-out` | Masuk halus |
| `ease-in` | Keluar halus |

---

## 13. Warna Prioritas & Kalender

### Prioritas Tiket
| Prioritas | Dot color class |
|-----------|----------------|
| Low | `bg-green-500` |
| Medium | `bg-blue-500` |
| High | `bg-red-500` |

Ukuran dot: `w-2 h-2` (0.5rem)

### Tipe Event Kalender
| Tipe | bg solid | text | bg light | border |
|------|----------|------|----------|--------|
| Meeting | `bg-blue-500` | `text-blue-700` | `bg-blue-50` | `border-blue-200` |
| Task | `bg-green-500` | `text-green-700` | `bg-green-50` | `border-green-200` |
| Deadline | `bg-purple-500` | `text-purple-700` | `bg-purple-50` | `border-purple-200` |
| Urgent | `bg-red-500` | `text-red-700` | `bg-red-50` | `border-red-200` |
| Reminder | `bg-yellow-500` | `text-yellow-700` | `bg-yellow-50` | `border-yellow-200` |

File: [resources/views/calendar/index.blade.php](../resources/views/calendar/index.blade.php)

---

## File-File Utama

| File | Keterangan |
|------|------------|
| [resources/css/app.css](../resources/css/app.css) | Entry point Tailwind, font global |
| [resources/css/login.css](../resources/css/login.css) | Style khusus halaman login |
| [resources/views/dashboard.blade.php](../resources/views/dashboard.blade.php) | Definisi CSS custom properties & utility classes |
| [resources/views/auth/login.blade.php](../resources/views/auth/login.blade.php) | Implementasi lengkap toast system |
| [resources/views/delivery/project/layouts/app.blade.php](../resources/views/delivery/project/layouts/app.blade.php) | Layout utama, sidebar, header |
| [resources/views/delivery/project/components/](../resources/views/delivery/project/components/) | Komponen button, modal, form |
| [vite.config.js](../vite.config.js) | Konfigurasi build (Tailwind v4 via `@tailwindcss/vite`) |

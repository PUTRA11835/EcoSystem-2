# JARVIES UI Sizing Reference
> Panduan ukuran, spacing, dan komponen UI JARVIES untuk konsistensi dengan EcoSystem.

---

## 1. Layout Utama

```
┌────────────────────────────────────────────────────┐
│  SIDEBAR (fixed, w-64 / 256px)  │  MAIN CONTENT    │
│  bg: gradient red-800→red-950   │  ml-64, flex-1   │
│                                 │                  │
│  Collapsed: w-16 (64px)         │  min-h-screen    │
└────────────────────────────────────────────────────┘
```

| Elemen | Kelas / Nilai |
|---|---|
| Sidebar lebar (expanded) | `w-64` = **256px** |
| Sidebar lebar (collapsed) | `w-16` = **64px** |
| Sidebar background | `from-red-800 via-red-900 to-red-950` |
| Main content margin-left | `ml-64` |
| Main content | `flex-1 flex flex-col min-h-screen` |
| Transition sidebar | `transition-all duration-300` |

---

## 2. Header / Navbar

| Elemen | Kelas / Nilai |
|---|---|
| Header background | `bg-white sticky top-0 z-40 shadow-sm border-b border-gray-100` |
| Header padding | `px-6 py-4` |
| Page title | `text-xl font-bold text-gray-900` |
| Page subtitle | `text-xs text-gray-500` |
| Hamburger button | `w-10 h-10 border-2 border-red-200 rounded-xl hover:border-red-800` |

---

## 3. Sidebar Logo & Nav

| Elemen | Kelas / Nilai |
|---|---|
| Logo section padding | `p-5 pb-4` |
| Logo icon container | `w-10 h-10 bg-white/20 rounded-xl` |
| Logo icon | `w-6 h-6 text-white` |
| App name | `font-bold text-lg` |
| App subtitle | `text-red-300 text-xs` |
| Nav padding | `py-6 px-4` |
| Nav item | `flex items-center gap-3 px-4 py-3 rounded-xl` |
| Nav item text | `font-medium` (inherit size) |
| Nav item active | `bg-white/20 font-semibold` |
| Nav item hover | `hover:bg-white/10` |
| Nav item hover animation | `translateX(4px)` |

---

## 4. Ticket Detail View — Layout 3 Kolom

```
┌─────────────────────────────────────────┬────────────┐
│  SIDEBAR TICKET LIST (w-64 sidebar)     │            │
│  ──────────────────────────────         │  MAIN      │
│  [ticket list items di sidebar kiri]    │  CONTENT   │
│                                         │            │
│  ─── MAIN: 2 panel ─────────────────── │            │
│  ┌──────────────────────┬────────────┐  │            │
│  │  MESSAGES THREAD     │ PROPERTIES │  │            │
│  │  flex-1              │ w-72/288px │  │            │
│  │                      │ xl:block   │  │            │
│  └──────────────────────┴────────────┘  │            │
└─────────────────────────────────────────┴────────────┘
```

### Content Area Height
```css
height: calc(100vh - 140px);
min-height: 500px;
```

### Messages Thread Panel
| Elemen | Kelas / Nilai |
|---|---|
| Container | `flex-1 flex flex-col bg-white rounded-xl border border-gray-200 shadow-sm` |
| Header padding | `px-6 py-4 border-b border-gray-200` |
| Ticket title | `text-base font-bold text-gray-900` |
| Ticket number | `text-sm text-gray-400 font-mono` |
| Messages area | `flex-1 overflow-y-auto px-6 py-4 space-y-4` |

### Properties Panel (kanan)
| Elemen | Kelas / Nilai |
|---|---|
| Container | `hidden xl:block w-72` = **288px** |
| Background | `bg-white rounded-xl border border-gray-200 shadow-sm overflow-y-auto shrink-0` |
| Padding | `p-5` |
| Section title | `text-xs font-bold text-gray-900 uppercase tracking-wide mb-4` |
| Items spacing | `space-y-3` |
| Label | `text-xs font-semibold text-gray-500 mb-1 block` |
| Value | `text-xs text-gray-700 px-2.5 py-1.5 bg-gray-50 rounded-lg border border-gray-200` |
| Visible breakpoint | `xl` = **≥ 1280px** |

---

## 5. Message Bubbles

| Elemen | Kelas / Nilai |
|---|---|
| Bubble max-width | `max-width: 85%` |
| Bubble word-break | `word-break: break-word` |
| Customer bubble | `background: #eff6ff; border-radius: 12px 12px 4px 12px` |
| Agent/helpdesk bubble | `background: #f9fafb; border-radius: 12px 12px 12px 4px` |
| Avatar size | `w-8 h-8` = **32px** |
| Avatar border-radius | `rounded-full` |
| Avatar font | `text-xs font-bold text-white` |
| Message gap | `gap-3 space-y-4` |
| Sender name | `text-sm font-semibold text-gray-900` |
| Timestamp | `text-xs text-gray-400` |
| Badge (Initial/Reply) | `text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded font-semibold` |
| Bubble padding | `p-3` |
| Content font | `text-sm text-gray-700` |

### Status Badge Colors
| Status | Kelas |
|---|---|
| Initial | `bg-gray-100 text-gray-600` |
| Open / In Progress | `bg-blue-100 text-blue-700` |
| Resolved | `bg-green-100 text-green-700` |
| Closed | `bg-gray-200 text-gray-500` |
| Awaiting validation | `bg-amber-100 text-amber-700` |

### Priority Badge Colors
| Priority | Kelas |
|---|---|
| Low | `bg-green-50 text-green-700 border-green-200` |
| Medium | `bg-blue-50 text-blue-700 border-blue-200` |
| High | `bg-red-50 text-red-700 border-red-200` |
| Very High | `bg-red-100 text-red-800 border-red-300` |

---

## 6. Reply / Comment Box

| Elemen | Kelas / Nilai |
|---|---|
| Container | `px-4 pt-2 pb-2` |
| Quill toolbar padding | `4px 8px` |
| Quill toolbar bg | `#f9fafb` |
| Quill editor font-size | `13px` |
| Quill editor min-height | `80px` |
| Quill editor max-height | `180px` |
| Quill editor padding | `8px 12px` |
| Quill line-height | `1.6` |
| Send button | `px-4 py-1.5 bg-red-700 text-white text-xs font-semibold rounded-lg hover:bg-red-800` |

---

## 7. Sidebar Ticket List Items

```
┌─────────────────────────────────────────┐
│ ● #pending   Description text...  14m  │
│   • Initial                             │
└─────────────────────────────────────────┘
```

| Elemen | Kelas / Nilai |
|---|---|
| Item padding | `padding: 8px 10px 8px 12px` |
| Item border-radius | `7px` |
| Item border-left (active) | `3px solid #ffffff` |
| Item border-left (hover) | `3px solid #b91c1c` |
| Item shadow | `0 1px 3px rgba(0,0,0,.08)` |
| Description | `text-xs font-semibold text-gray-800` |
| Timestamp | `text-[10px] text-gray-400` |
| Status badge | `text-[10px] px-1.5 py-0.5 rounded-full font-semibold` |

---

## 8. Sidebar Ticket Search

| Elemen | Kelas / Nilai |
|---|---|
| Padding container | `px-4 pt-4 pb-3 border-b border-red-700/50` |
| Input | `w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-sm text-white` |
| Placeholder color | `placeholder-white/50` |
| Back link | `px-3 py-2 rounded-lg text-white/80 hover:bg-white/10 text-sm font-medium` |

---

## 9. Typography Scale

| Kelas | Ukuran | Penggunaan |
|---|---|---|
| `text-[10px]` | 10px | Badge micro, timestamp kecil |
| `text-xs` | 12px | Label, badge, meta info, properties value |
| `text-sm` | 14px | Body text, nav item, input |
| `text-base` | 16px | Ticket title di header panel |
| `text-xl` | 20px | Page title di navbar |
| `text-lg` | 18px | App name di sidebar |
| `text-2xl` | 24px | Section heading besar |

**Base font-size:** `14px` (diset di `:root { --base-font-size: 14px }`)

---

## 10. Warna Utama (Brand)

```css
:root {
    --primary-color:   #c62828;   /* red-800 */
    --primary-rgb:     198, 40, 40;
    --primary-dark:    #991b1b;   /* red-800 dark */
    --bg-color:        #f9fafb;   /* gray-50 */
    --text-color:      #111827;   /* gray-900 */
    --card-bg:         #ffffff;
}
```

| Elemen | Warna |
|---|---|
| Sidebar gradient start | `#991b1b` (red-800) |
| Sidebar gradient mid | `#7f1d1d` (red-900) |
| Sidebar gradient end | `#450a0a` (red-950) |
| Primary button | `bg-red-700` (#b91c1c) hover `bg-red-800` |
| Link/active nav | `bg-white/20` |
| Info banner bg | `bg-amber-50 border-amber-200 text-amber-800` |
| Page bg | `bg-gray-50` (#f9fafb) |

---

## 11. Scrollbar Custom

```css
::-webkit-scrollbar        { width: 6px; height: 6px; }
::-webkit-scrollbar-track  { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb  { background: #c62828; border-radius: 10px; border: 2px solid #f1f1f1; }
```

---

## 12. Toast Notification

| Elemen | Nilai |
|---|---|
| Position | `fixed top: 1.5rem, right: 1.5rem` |
| Max-width | `22rem` = 352px |
| Padding | `0.875rem 1rem` |
| Border-radius | `0.75rem` |
| Border | `1.5px solid #e5e7eb` |
| Shadow | `0 4px 16px rgba(0,0,0,0.08)` |
| Animation in | `translateX(0)` opacity 1, cubic-bezier(0.34, 1.56, 0.64, 1) 0.4s |
| Animation out | `translateX(110%)` opacity 0 |

| Tipe | Background | Border |
|---|---|---|
| Success | `#f0fdf4` | `#86efac` |
| Error | `#fff1f1` | `#fca5a5` |
| Warning | `#fffbeb` | `#fcd34d` |
| Info | `#eff6ff` | `#93c5fd` |

---

## 13. Cards & Containers

| Elemen | Kelas |
|---|---|
| Standard card | `bg-white rounded-xl border border-gray-200 shadow-sm` |
| Padding card | `p-5` atau `p-6` |
| Rounded standar | `rounded-xl` (12px) |
| Rounded kecil | `rounded-lg` (8px) |
| Rounded badge | `rounded-md` (6px) atau `rounded-full` |
| Border standar | `border border-gray-200` |
| Shadow standar | `shadow-sm` |
| Shadow card hover | `shadow-md` |

---

## 14. Responsive Breakpoints

| Breakpoint | Min-width | Penggunaan |
|---|---|---|
| (default) | 0px | Mobile |
| `sm` | 640px | - |
| `md` | 768px | - |
| `lg` | 1024px | - |
| `xl` | 1280px | Properties panel muncul |
| `2xl` | 1536px | - |

> **Catatan:** Properties panel (`w-72`) hanya tampil di `xl` ke atas. Di bawah `xl`, panel ini hidden.

---

## 15. Z-Index Stack

| Elemen | Z-index |
|---|---|
| Toast notification | `z-9999` |
| Sidebar | `z-50` |
| Header/Navbar | `z-40` |
| Dropdown/Modal | `z-50` |

---

## 16. Font

```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```
Diload dari Google Fonts: `Inter` weight 300, 400, 500, 600, 700, 800.

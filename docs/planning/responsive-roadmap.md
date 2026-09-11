# Responsive UI Roadmap — EcoSystem

> **Tujuan besar:** seluruh tampilan EcoSystem responsive & nyaman dipakai di laptop, PC, tablet, dan HP — termasuk tombol/toolbar yang menyesuaikan ukuran layar.
>
> Dokumen ini memecah sisa pekerjaan menjadi **fase-fase independen**. Tiap fase bisa dikerjakan di **chat berbeda**: baca bagian "Konteks Bersama" + "Resep Verifikasi" di atas, lalu tempel **KICKOFF PROMPT** fase yang dituju.

---

## Status keseluruhan

| Area | Status |
|---|---|
| **Layout shell** (sidebar + header, membungkus semua 140 halaman) | ✅ SELESAI |
| **Home dashboard** | ✅ Sudah responsive (tidak diubah) |
| **Master Employee & Customer** (index, show, sections) | ✅ SELESAI |
| **Fase 1 — Calendar (bulanan & mingguan)** | ✅ SELESAI |
| **Fase 2 — Calendar Timesheets** | ✅ SELESAI |
| **Fase 3 — Halaman Auth** | ✅ SELESAI |
| **Fase 4 — Modal / form grid kolom-tetap** | ✅ SELESAI |
| **Fase 5 — Poles sub-grid (opsional, low priority)** | ✅ SELESAI (diverifikasi — tak ada perubahan markup diperlukan) |

---

## Konteks Bersama (WAJIB dibaca tiap fase)

- **Stack:** Laravel 11 + Blade + Tailwind CSS via **CDN** (`cdn.tailwindcss.com` di `resources/views/dashboard.blade.php`). **Tidak ada build step** — utilities responsive (`sm:` `md:` `lg:` `-translate-x-full` dll) dan `<style>` media query custom sama-sama langsung jalan.
- **Breakpoint yang dipakai konsisten di project ini:**
  - `< 480px` → HP kecil (target 1 kolom)
  - `480–767px` → HP besar (2 kolom)
  - `768–1023px` (`md`) → tablet (3 kolom)
  - `≥ 1024px` (`lg`) → desktop (layout penuh)
- **Layout shell** (`dashboard.blade.php`) sudah responsive: sidebar **docked** di `lg`, jadi **drawer off-canvas + backdrop** di bawah `lg`. `toggleSidebar()` sadar-viewport (`isDesktopViewport()` = width ≥ 1024). **Jangan** ubah perilaku ini saat kerja fase.
- **Utility `.form-grid`** (didefinisikan sekali di `<style>` `dashboard.blade.php`): dipakai untuk **form padat `grid-cols-6`**. Tempel `form-grid` di samping `grid grid-cols-6 gap-4`; di bawah `lg` kolom menyusut (1/2/3) dan tiap child dipaksa `grid-column:auto` sehingga `col-span-*` tak meluber. **Hanya untuk grid-cols-6.** Untuk grid 2/3 kolom, pakai prefix responsive biasa (lihat Fase 4).
- **Pola tabel di project ini sudah aman:** tabel lebar dibungkus `overflow-auto`/`overflow-x-auto` + `min-width` (mis. `ticket/index` min-width 2200px). **Jangan** ubah tabel yang sudah begini — biarkan scroll horizontal.
- **Pola repo:** `@extends('dashboard')`, `@section('content')`, `session('user')` (bukan `Auth::`), form dalam dashboard = AJAX JSON. Jangan sentuh logika JS/fetch — pekerjaan ini **CSS/markup class only**.
- **Aturan emoji:** jangan emoji UTF-8 literal di template (mojibake) — pakai inline SVG. (lihat `memory/emoji_mojibake.md`)
- Memori konvensi: `memory/responsive-ui-jul2026.md`.

---

## Resep Verifikasi (reusable — dipakai tiap fase)

Halaman butuh auth custom + DB, jadi login penuh sulit. Karena perubahan **murni CSS/markup class**, verifikasi dilakukan dengan mendrive **markup + CSS asli** di Chrome headless via `puppeteer-core`. Ini surface sebenarnya (DOM browser).

**Yang tersedia di mesin dev ini:**
- Chrome: `C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`
- Node 22, `puppeteer-core` (install di scratchpad: `npm install puppeteer-core@23`)

**Langkah:**
1. Buat harness HTML di scratchpad: sertakan `<script src="https://cdn.tailwindcss.com"></script>` + `<style>` yang menyalin media query relevan dari `dashboard.blade.php`, lalu **tempel markup asli** section yang diubah (buang JS/fetch, isi placeholder).
2. Drive dengan puppeteer di lebar **375 / 700 / 900 / 1280px**; ukur `getBoundingClientRect`, `scrollWidth - clientWidth` (overflow), dan screenshot.
3. **Kriteria lulus universal:** `cardOverflow === 0` (tak ada overflow horizontal tak disengaja) di semua lebar, dan layout desktop (≥1024) **identik** dengan sebelum perubahan.

Contoh driver siap-pakai ada di histori commit (harness form-grid). Pola: loop viewport → `page.goto(file://...)` → `page.evaluate(...)` → `page.screenshot(...)`.

---

## FASE 1 — Calendar (bulanan & mingguan)

**Prioritas: 🔴 TINGGI (rusak nyata di HP).**

**Masalah:** grid kalender fixed tanpa scroll & tanpa media query → sel remuk di HP.
- [`resources/views/calendar/index.blade.php`](../../resources/views/calendar/index.blade.php)
  - baris ~53: header hari `grid grid-cols-7` (fixed)
  - baris ~64: `#calendarGrid` `grid grid-cols-7` (`min-height:600px`)
  - baris ~160, ~200: modal `grid-cols-2` (poles)
- [`resources/views/calendar/events.blade.php`](../../resources/views/calendar/events.blade.php)
  - baris ~60, ~71: bulanan `grid grid-cols-7`
  - baris ~76, ~82: mingguan `grid grid-cols-8` (kolom jam + 7 hari)
  - baris ~255, ~273: modal `grid-cols-2` (poles)

**Pendekatan yang disarankan:** kalender secara natural 7/8 kolom → **jangan** paksa jadi 1 kolom. Bungkus grid dalam container `overflow-x-auto` dan beri grid `min-w-[…]` supaya kolom tetap cukup lebar dan bisa di-scroll horizontal di HP:
- Header hari + `#calendarGrid` dibungkus **satu** wrapper `overflow-x-auto`, lalu masing-masing beri `min-w-[640px]` (7 kolom ≈ 90px+).
- Week view `grid-cols-8` → wrapper `overflow-x-auto` + `min-w-[720px]`.
- **Penting:** header hari dan grid tanggal harus punya `min-w-*` yang **sama** dan berada dalam wrapper scroll yang sama agar kolomnya tetap sejajar saat scroll.
- Modal `grid-cols-2` → `grid-cols-1 sm:grid-cols-2` (poles kecil).

**Acceptance Criteria:**
1. Di 375px: kalender bisa di-scroll horizontal, sel tetap terbaca (tanggal + event chip), header hari sejajar dengan kolom tanggal.
2. Tak ada overflow yang membuat **seluruh halaman** geser horizontal (hanya container kalender yang scroll).
3. Di ≥1024px tampilan **identik** dengan sekarang.
4. Toggle month/week (di `events`) tetap berfungsi, keduanya rapi.

**KICKOFF PROMPT (tempel di chat Fase 1):**
```
Kerjakan Fase 1 (Calendar) dari docs/planning/responsive-roadmap.md.
Baca dulu bagian "Konteks Bersama" + "Resep Verifikasi" + "FASE 1" di dokumen itu.

Target: resources/views/calendar/index.blade.php & calendar/events.blade.php.
Bungkus grid kalender (grid-cols-7 bulanan, grid-cols-8 mingguan) dalam wrapper
overflow-x-auto + min-w-* agar bisa scroll horizontal & terbaca di HP; header hari
harus tetap sejajar dengan kolom tanggal. Poles modal grid-cols-2 -> grid-cols-1 sm:grid-cols-2.
JANGAN ubah JS/logika kalender — CSS/markup class saja. Verifikasi headless Chrome
di 375/700/900/1280px (cardOverflow==0, desktop identik), lampirkan screenshot.
```

---

## FASE 2 — Calendar Timesheets

**Prioritas: 🔴 TINGGI (kompleks).**

**File:** [`resources/views/calendar/timesheets.blade.php`](../../resources/views/calendar/timesheets.blade.php) (~1400 baris).

**Yang perlu di-audit & diperbaiki:**
- ~48 elemen lebar-tetap (`w-64`/`min-w-[…]`/`style="width:…"`) — cek mana yang bikin overflow di HP; ubah ke `w-full sm:w-…` bila perlu.
- `<table>` di file ini — pastikan dibungkus `overflow-auto`/`overflow-x-auto` (+`min-width` bila kolom banyak). Kalau belum, bungkus.
- Grid stat sudah responsive (`grid-cols-2 md:grid-cols-4`, `md:grid-cols-5`) — verifikasi saja.
- Panel dua-kolom `grid grid-cols-1 md:grid-cols-2` (~815) sudah responsive — verifikasi.
- Modal `grid-cols-2 gap-3` (~1383, 1393) → `grid-cols-1 sm:grid-cols-2`.

**Acceptance Criteria:**
1. Di 375px tak ada overflow horizontal halaman; tabel (bila ada) scroll dalam containernya sendiri.
2. Filter/toolbar tidak terpotong (tombol wrap bila perlu).
3. Desktop identik.

**KICKOFF PROMPT (tempel di chat Fase 2):**
```
Kerjakan Fase 2 (Calendar Timesheets) dari docs/planning/responsive-roadmap.md.
Baca "Konteks Bersama" + "Resep Verifikasi" + "FASE 2" dulu.

Target: resources/views/calendar/timesheets.blade.php. Audit ~48 elemen lebar-tetap
(w-64/min-w-[]/style width) yang bikin overflow di HP -> w-full sm:*; pastikan <table>
dibungkus overflow-auto (+min-width kalau kolom banyak); modal grid-cols-2 -> grid-cols-1 sm:grid-cols-2.
CSS/markup class saja, jangan sentuh JS. Verifikasi headless Chrome 375/700/900/1280px.
```

---

## FASE 3 — Halaman Auth

**Prioritas: 🟡 MENENGAH (verifikasi + perbaiki bila perlu).**

**File (layout custom, DI LUAR `dashboard`):**
- [`resources/views/auth/login.blade.php`](../../resources/views/auth/login.blade.php) — dua-panel (brand + form), `body{display:flex;height:100vh;overflow:hidden}`, ada beberapa `@media`.
- [`resources/views/auth/forgot-password.blade.php`](../../resources/views/auth/forgot-password.blade.php)
- [`resources/views/auth/change-password.blade.php`](../../resources/views/auth/change-password.blade.php)
- [`resources/views/auth/check-email.blade.php`](../../resources/views/auth/check-email.blade.php)
- [`resources/views/auth/partials/brand-panel.blade.php`](../../resources/views/auth/partials/brand-panel.blade.php)

**Yang perlu dicek:**
- Di HP (≤ ~640px): panel brand sebaiknya **disembunyikan** dan form jadi full-width & center. Pastikan `@media` yang ada sudah melakukan ini; kalau belum, tambahkan.
- `body{overflow:hidden;height:100vh}` — pastikan form tetap muat / bisa scroll di layar pendek (HP landscape / keyboard muncul). Pertimbangkan `min-height:100vh` + `overflow:auto` di mobile.
- Input & tombol full-width, target sentuh cukup besar.

**Acceptance Criteria:**
1. 375px: form login center, full-width, tak ada horizontal scroll; brand panel tidak memaksa layout.
2. Layar pendek: seluruh form (termasuk tombol submit) tetap terjangkau.
3. Desktop dua-panel identik.

**KICKOFF PROMPT (tempel di chat Fase 3):**
```
Kerjakan Fase 3 (Auth) dari docs/planning/responsive-roadmap.md.
Baca "Konteks Bersama" + "Resep Verifikasi" + "FASE 3" dulu.

Target: resources/views/auth/*.blade.php (+ partials/brand-panel). Ini layout custom
di luar dashboard. Pastikan di HP panel brand hidden & form full-width center, dan
form tetap terjangkau di layar pendek (hati-hati body overflow:hidden + height:100vh).
Verifikasi headless Chrome 375/700/1280px; screenshot login mobile + desktop.
```

---

## FASE 4 — Modal / form grid kolom-tetap

**Prioritas: 🟡 MENENGAH (poles; sempit di HP kecil tapi masih fungsional).**

**Masalah:** beberapa form/modal pakai `grid-cols-2` atau `grid-cols-3` **fixed** (tanpa fallback 1 kolom) → sempit di HP.

**File & lokasi:**
- [`resources/views/staging/index.blade.php`](../../resources/views/staging/index.blade.php) — `grid-cols-3` (~51 filter, ~502 detail), `grid-cols-2` (~579)
- [`resources/views/staging/rejected.blade.php`](../../resources/views/staging/rejected.blade.php) — `grid-cols-2` (~290)
- [`resources/views/admin/sla/config.blade.php`](../../resources/views/admin/sla/config.blade.php) — `grid-cols-2` (~217, 239, 298)
- [`resources/views/rpmo/periods/index.blade.php`](../../resources/views/rpmo/periods/index.blade.php) — `grid-cols-2` (~384, 408, 918)
- [`resources/views/ticket/index.blade.php`](../../resources/views/ticket/index.blade.php) — `grid-cols-2` field modal (~470, 515)
- [`resources/views/ticket/show.blade.php`](../../resources/views/ticket/show.blade.php) — `grid-cols-2` (~1903, 1924)

**Pendekatan (bukan `.form-grid` — itu khusus grid-cols-6):** tambahkan fallback 1 kolom:
- `grid grid-cols-2 gap-*` → `grid grid-cols-1 sm:grid-cols-2 gap-*`
- `grid grid-cols-3 gap-*` → `grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-*`
- **Hati-hati:** hanya untuk grid yang isinya **field form / konten teks**. **Jangan** ubah grid yang memang harus tetap N kolom (mis. tab filter yang sengaja sejajar) tanpa cek visual. Verifikasi tiap perubahan.
- Cek juga `col-span-*` di dalamnya; kalau ada, tambah fallback (`col-span-1 sm:col-span-2`).

**Acceptance Criteria:**
1. Di 375px field-field modal menumpuk 1 kolom, terbaca, tak meluber.
2. Modal tetap `max-h-[90vh] overflow-y-auto` (scroll vertikal) — jangan hilangkan.
3. Desktop identik.

**KICKOFF PROMPT (tempel di chat Fase 4):**
```
Kerjakan Fase 4 (modal/form grid) dari docs/planning/responsive-roadmap.md.
Baca "Konteks Bersama" + "Resep Verifikasi" + "FASE 4" dulu.

Target: grid-cols-2/3 fixed di staging/index, staging/rejected, admin/sla/config,
rpmo/periods/index, ticket/index, ticket/show (lihat baris di dokumen). Tambah fallback
1 kolom: grid-cols-2 -> grid-cols-1 sm:grid-cols-2; grid-cols-3 -> grid-cols-1 sm:grid-cols-2 md:grid-cols-3.
JANGAN pakai .form-grid (itu khusus grid-cols-6). Cek visual tiap grid — jangan ubah grid
yang memang harus tetap sejajar. Verifikasi headless Chrome 375/1280px.
```

---

## FASE 5 — Poles sub-grid (opsional, low priority)

**Prioritas: 🟢 RENDAH (isinya pendek, umumnya masih terbaca).**

- [`resources/views/admin/index.blade.php`](../../resources/views/admin/index.blade.php) — sub-grid `grid-cols-3/2` (~27, 62, 97)
- [`resources/views/admin/sessions.blade.php`](../../resources/views/admin/sessions.blade.php) — `grid-cols-3` detail (~176)
- [`resources/views/admin/backup.blade.php`](../../resources/views/admin/backup.blade.php) — blok `grid-cols-2` (~72, 169, 1146)

**Pendekatan:** sama seperti Fase 4 (tambah fallback 1 kolom) **hanya bila** terlihat sempit saat diverifikasi. Boleh dilewati kalau sudah cukup.

**HASIL VERIFIKASI (Jul 2026) — TAK ADA PERUBAHAN MARKUP.** Ketujuh sub-grid di-boot dengan markup aslinya ke harness Chrome headless (Tailwind CDN) & diuji 375 / 700 / 1280px. Di **375px `pageOverflow == 0`** untuk semua, dan tiap grid tetap terbaca → sesuai aturan fase ("kalau sudah terbaca, BIARKAN, jangan ubah demi konsistensi"). Rincian keputusan:

| Grid | 375px minChildW | Keputusan & alasan |
|---|---|---|
| `admin/index` ~27 (`grid-cols-3` stat Activity Log) | 98px | **Biarkan** — hanya angka besar + label pendek (Total/Success/Failed), 3 sejajar tetap terbaca; ini stat card yang memang sengaja berdampingan. |
| `admin/index` ~62 (`grid-cols-3` stat Sessions) | 98px | **Biarkan** — sama; Active/Logged In/Guest muat 1 baris. |
| `admin/index` ~97 (`grid-cols-2`) | 309px (full) | **Biarkan** — semua child `col-span-2` → praktis sudah 1 kolom full-width; tak ada yang perlu diubah. |
| `admin/sessions` ~176 (`grid-cols-3` label:value modal) | 90px | **Biarkan** — layout label 1/3 : value 2/3 memang disengaja; value pakai `break-all`; terbaca penuh, `pageOverflow==0` (grid-overflow 7px terkurung di dalam modal, tak menggeser halaman & tak ada teks terpotong). |
| `admin/backup` ~72 (`grid-cols-2` Year/Month select) | 149px | **Biarkan** — dua `<select>` `w-full` muat berdampingan; nilai terpanjang ("September") tetap terlihat. |
| `admin/backup` ~169 (`grid-cols-2` Tahun/Bulan select) | 151px | **Biarkan** — sama seperti ~72. |
| `admin/backup` ~1146 (`grid-cols-2` hasil import) | 134px | **Biarkan** — 4 item ringkas (mis. "12 tiket baru"), 2 kolom tetap terbaca. |

Desktop (≥1024px) otomatis identik karena markup tidak diubah.

**KICKOFF PROMPT (tempel di chat Fase 5):**
```
Kerjakan Fase 5 (poles sub-grid, low priority) dari docs/planning/responsive-roadmap.md.
Baca "Konteks Bersama" + "Resep Verifikasi" + "FASE 5" dulu.
Target: admin/index, admin/sessions, admin/backup sub-grid grid-cols-2/3. Tambah fallback
1 kolom HANYA bila verifikasi 375px menunjukkan sempit. CSS/markup class saja.
```

---

## Referensi cepat — SUDAH OK (jangan diutak-atik)

- **Home dashboard**, **Master** (Employee/Customer, selesai), **profile/edit**, **SLA report**, **reporting**, **md-recap**.
- **Tabel list** semua sudah dibungkus scroll (`overflow-auto`/`overflow-x-auto` + `min-width`): `ticket/index`, `reporting`, semua `management/employee/*`, `management/roles`, `holidays`, `hidden-tickets`, `admin/failed-jobs`, `admin/sessions`, `admin/activity-log`, dll.
- **PDF templates** (`admin/sla/*-pdf`) — hanya untuk cetak, abaikan.

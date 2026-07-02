# Jarvies — Deliverable Folder Link di Panel Properties

## Overview

Customer (Jarvies) minta akses ke folder OneDrive tempat file **deliverable** ticket
disimpan. Folder dibuat lazy oleh EcoSystem saat file deliverable pertama diupload:

```
DELIVERY SUPPORT / CUSTOMER DELIVERABLE / {NNN NAMA CUSTOMER} / TICKETING / {ticket_number} / Deliverable
```

**Keputusan scope (disepakati):**

- **Level akses = folder ticket saja** (`.../TICKETING/{ticket_number}`), **bukan** folder
  customer. Anonymous share link dibuat pada folder ticket, jadi customer yang membuka
  link **hanya** melihat isi folder ticket ini — tidak bisa naik ke folder customer
  (`011 AIRNAV INDONESIA`) apalagi ke `CUSTOMER DELIVERABLE`. Dengan begitu **customer
  tidak bisa melihat folder customer lain**. Prefix `Delivery Support > CUSTOMER
  DELIVERABLE` juga tidak pernah tampil ke customer anonim (hanya tampil untuk employee
  yang login ke tenant).
- **Hak akses = edit** (upload/download). Link `type='edit'`, sama dengan link yang
  dipakai tombol "Open OneDrive Folder" di EcoSystem.

> Ini adalah link **`onedrive_folder_url`** yang sudah tersimpan di tabel `ticket`
> (dibuat di `TicketDeliverableController::store`). Tidak ada folder/link baru yang
> dibuat khusus Jarvies — kita hanya mengekspos link yang sama.

---

> **Penting — Jarvies pakai DATABASE yang sama dgn EcoSystem.** Kolom
> `ticket.onedrive_folder_url` langsung terbaca oleh model Eloquent Jarvies. Jadi
> tombol di web Jarvies (Blade) membaca kolom itu **langsung dari DB**, TIDAK perlu
> memanggil API EcoSystem.

## 1. Perubahan EcoSystem (SUDAH DIKERJAKAN)

`app/Http/Controllers/TicketController.php` → method `show()` (endpoint
`GET /api/jarvies/tickets/{id}`) juga mengekspos field ini di response JSON, untuk
konsumen API lain (mis. mobile) — meskipun web Jarvies tidak memakainya:

```php
'deliverable_folder_url' => $ticket->onedrive_folder_url,   // string|null
'has_deliverable_folder' => !empty($ticket->onedrive_folder_url), // bool
```

- `deliverable_folder_url` — `null` selama belum ada file deliverable yang diupload
  (folder di-create lazy saat upload pertama). Setelah ada minimal 1 file, berisi URL
  anonymous edit-link ke folder ticket.

---

## 2. Perubahan Jarvies (SUDAH DIKERJAKAN)

**File:** `resources/views/tickets/show.blade.php` (repo `JARVIES-main`), panel
**Properties**, disisipkan sebelum blok `Customer Actions`. Tombol membaca
`$ticket->onedrive_folder_url` langsung dari DB dan hanya dirender jika kolomnya terisi
(ditampilkan tanpa memandang status agar deliverable tetap bisa diakses walau tiket
sudah closed):

```blade
@if(!empty($ticket->onedrive_folder_url))
<div class="pt-4 border-t border-gray-100">
    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Deliverable</p>
    <a href="{{ $ticket->onedrive_folder_url }}" target="_blank" rel="noopener noreferrer"
       class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold border transition-all
              bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
        </svg>
        Open Deliverable Folder
    </a>
    <p class="mt-1.5 text-[11px] text-gray-400 leading-snug">
        Berisi dokumen deliverable untuk tiket ini.
    </p>
</div>
@endif
```

### Catatan

- `target="_blank"` + `rel="noopener noreferrer"` — buka OneDrive di tab baru dengan aman.
- Kalau `onedrive_folder_url` masih `null` (belum ada deliverable), tombol tidak
  dirender — tidak perlu tombol disabled.
- Pakai inline SVG (bukan emoji UTF-8 literal) sesuai aturan proyek anti-mojibake.

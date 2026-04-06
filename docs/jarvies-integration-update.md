# Panduan Integrasi Jarvies — Update Terbaru

> **Tanggal:** 2026-02-26
> **Project:** EcoSystem (employee/admin side) ↔ Jarvies (customer side)
> **Database:** Shared — satu database untuk kedua project

---

## Ringkasan Cepat — Apa yang Berubah

| Area | EcoSystem (Employee) | Jarvies (Customer) |
|---|---|---|
| `ticket_type` kolom baru | ✅ Sudah ada di DB & UI | ⚠️ Perlu tampilkan di detail tiket |
| `ticket_number` sekarang nullable | ✅ Migration sudah dijalankan | ✅ Tidak ada lagi DB error saat buat tiket |
| Staging: priority & type null untuk email | ✅ Implemented | ⚠️ Handle null dengan graceful |
| Staging approve: type & priority required | ✅ Implemented | ℹ️ Customer tidak approve — no action |
| Email body di staging | ✅ Iframe viewer di modal | ℹ️ Customer tidak lihat staging |
| Team Members | ✅ PIC & Helpdesk bisa add/remove | ⚠️ Tampilkan members di detail tiket |
| My Tickets untuk PIC/member | ✅ Fixed (no DSM check) | ⚠️ Customer sudah OK — no change |
| Pesan email: `message_html` | ✅ Tersimpan & dikirim | ⚠️ Render `message_html` bukan plain text |

---

## PERUBAHAN 1 — Kolom `ticket_type` Baru

### Database
Kolom baru `ticket_type` (nullable, varchar 50) pada tabel `ticket`.

**Migration:** `database/migrations/2026_02_25_200000_add_ticket_type_to_ticket.php`

**Nilai valid:** `Incident` | `Service Request` | `Change Request` | `Consult`

> Berbeda dari tipe delivery (`AMS`, `MO`, `ATS`, dll.) yang ada di tabel `delivery_support`.

### API — Field baru di semua response ticket

**`GET /api/tickets`**, **`GET /api/tickets/{id}`**, **`GET /api/tickets/my`** sekarang mengembalikan:
```json
{
  "ticket_id": 54,
  "ticket_number": "2602-ABCD-0001",
  "ticket_type": "Incident",
  "ticket_priority": "High",
  "status": "open",
  "jarvies_status": "in process"
}
```

### Yang perlu dilakukan Jarvies
- Tampilkan `ticket_type` di halaman detail tiket dan daftar tiket sebagai badge/label
- Handle nilai `null` — ticket lama atau yang baru dibuat via email belum tentu punya type
- Warna badge yang disarankan (konsisten dengan EcoSystem):

| Nilai | Warna |
|---|---|
| Incident | Merah (`bg-red-50 text-red-600`) |
| Service Request | Indigo (`bg-indigo-50 text-indigo-600`) |
| Change Request | Amber (`bg-amber-50 text-amber-600`) |
| Consult | Teal (`bg-teal-50 text-teal-600`) |

---

## PERUBAHAN 2 — Staging Ticket: Priority & Type Null untuk Email

### Context
Ketika email masuk dari customer melalui Microsoft Graph, email tersebut disimpan sebagai **staging ticket** (status `unvalidated`) sebelum divalidasi oleh helpdesk. Email tidak memiliki metadata priority/type, sehingga keduanya disimpan sebagai `null`.

### Perubahan behavior

| Channel | Sebelum | Sekarang |
|---|---|---|
| Web (customer submit form) | priority = nilai yang dipilih customer | **sama — tidak berubah** |
| Email (dari inbox M365) | priority = `"Medium"` (default salah) | priority = `null` |
| Email | type = tidak ada | type = `null` |

Helpdesk **wajib** mengisi type + priority saat approve staging → baru menjadi ticket resmi.

### API — `GET /api/staging-tickets/{id}` — Field tambahan

```json
{
  "id": 12,
  "status": "unvalidated",
  "channel": "email",
  "ticket_priority": null,
  "ticket_type": null,
  "description": "Subject email di sini",
  "submitted_by_email": "customer@example.com",
  "sender_name": "Budi Santoso",
  "cc_emails": "cc1@example.com, cc2@example.com",
  "email_body_html": "<html>...isi email lengkap...</html>",
  "has_attachments": true,
  "created_at": "2026-02-26T08:00:00"
}
```

### API — `POST /api/staging-tickets/{id}/approve` — Sekarang wajib

```json
{
  "ticket_type": "Incident",
  "ticket_priority": "High"
}
```

> ⚠️ Sebelumnya optional, sekarang `required`. Jika tidak diisi akan 422 Validation Error.

### Yang perlu dilakukan Jarvies
- Saat menampilkan daftar tiket customer, handle `ticket_priority = null` → tampilkan `"—"` bukan crash
- Saat menampilkan detail tiket, handle `ticket_type = null` → tampilkan `"—"` atau `"Not set"`
- Setelah admin approve staging → `ticket_priority` dan `ticket_type` sudah terisi → refresh data

---

## PERUBAHAN 3 — Team Members Ticket

### Konsep
Tiap ticket sekarang bisa punya tim yang mengerjakan:
- **PIC (Agent)** — satu orang, disimpan di `ticket.employee_id`
- **Members** — banyak orang, disimpan di tabel `ticket_member`

Keduanya sekarang muncul di **My Tickets** masing-masing employee.

### API — Field `members` di response ticket

**`GET /api/tickets/{id}`** mengembalikan:
```json
{
  "ticket_id": 54,
  "employee_id": 10,
  "employee": {
    "employee_id": 10,
    "employee_name": "Andi Prasetyo"
  },
  "members": [
    { "employee_id": 15, "employee_name": "Budi Santoso" },
    { "employee_id": 22, "employee_name": "Citra Dewi" }
  ],
  "member_ids": [15, 22]
}
```

### API Endpoints baru untuk manage members

| Method | Endpoint | Permission | Body |
|---|---|---|---|
| `POST` | `/api/tickets/{id}/members` | Admin / PIC / Helpdesk | `{ "employee_id": 15 }` |
| `DELETE` | `/api/tickets/{id}/members/{employeeId}` | Admin / PIC / Helpdesk | — |

Response dari kedua endpoint mengembalikan daftar members terbaru:
```json
{
  "success": true,
  "data": [
    { "employee_id": 15, "employee_name": "Budi Santoso" }
  ]
}
```

> Customer **tidak bisa** add/remove members → 403 Forbidden jika dicoba.

### Yang perlu dilakukan Jarvies
- Tampilkan Agent (PIC) dari `employee.employee_name` di detail tiket
- Tampilkan Members dari array `members[]`
- Tampilkan sebagai read-only untuk customer — tidak perlu tombol add/remove

---

## PERUBAHAN 4 — Tampilan Email di Pesan Tiket ⚠️ PENTING

### Context
Ketika tiket berasal dari email, pesan pertama tersimpan di `ticket_message` dengan dua kolom:
- `message` → plain text (hasil strip_tags dari HTML)
- `message_html` → HTML asli email (dengan styling, gambar inline, dll.)

### API — `GET /api/tickets/{ticketId}/messages`

```json
{
  "id": 1,
  "sender_type": "customer",
  "sender_name": "Budi Santoso",
  "sender_email": "budi@example.com",
  "message": "Halo, saya ingin melaporkan masalah...",
  "message_html": "<div style='font-family:Arial'>Halo, saya <b>ingin melaporkan</b> masalah...</div>",
  "channel": "email",
  "cc_emails": "cc@example.com",
  "is_internal_note": false,
  "attachments": [
    {
      "id": 5,
      "file_name": "screenshot.png",
      "attachment_type": "image",
      "is_inline": false,
      "url": "/api/ticket-attachments/5"
    }
  ],
  "created_at": "2026-02-26T08:00:00"
}
```

### Aturan rendering pesan — KONSISTEN antara EcoSystem & Jarvies

| Kondisi | Yang ditampilkan |
|---|---|
| `channel = "email"` dan `message_html` tidak null | Render `message_html` sebagai HTML |
| `channel = "email"` dan `message_html` null | Fallback ke `message` (plain text) |
| `channel = "web"` | Render `message` sebagai HTML (dari Quill editor) |
| `is_internal_note = true` | **JANGAN tampilkan ke customer** |

### Cara render email HTML yang aman di Jarvies

```html
<!-- Opsi 1: iframe sandbox (paling aman, mencegah script) -->
<iframe
  id="emailFrame"
  sandbox="allow-same-origin"
  style="width:100%; min-height:200px; border:none;"
></iframe>
<script>
  const iframe = document.getElementById('emailFrame');
  iframe.srcdoc = message.message_html;
  // Auto-resize setelah load
  iframe.addEventListener('load', () => {
    try {
      const h = iframe.contentDocument?.documentElement?.scrollHeight;
      if (h) iframe.style.minHeight = Math.min(h + 20, 600) + 'px';
    } catch {}
  }, { once: true });
</script>

<!-- Opsi 2: div dengan DOMPurify sanitize -->
<div class="email-body">
  <!-- innerHTML = DOMPurify.sanitize(message.message_html) -->
</div>
```

### Yang perlu dilakukan Jarvies
- Untuk pesan `channel="email"` → gunakan `message_html` (bukan `message` plain text)
- Implementasikan iframe sandbox atau DOMPurify untuk keamanan rendering HTML
- Untuk pesan web/Quill → gunakan `message` (sudah HTML dari editor)
- **Wajib**: Jangan tampilkan pesan `is_internal_note=true` ke customer
- Tampilkan `cc_emails` jika ada (info siapa yang di-CC dalam email)
- Inline attachments (`is_inline=true`) biasanya sudah ter-embed di `message_html` via `cid:`

---

## PERUBAHAN 5 — My Tickets untuk Employee/Helpdesk (Internal Only)

> Ini perubahan internal EcoSystem. **Tidak ada dampak ke Jarvies.**

**Yang berubah di `GET /api/tickets/my`:**
- Sekarang bekerja untuk role 2 (employee), 6, 7 (helpdesk) **tanpa** DSM qualification check
- Menampilkan ticket dimana user adalah **PIC** (`employee_id`) ATAU **member** (`ticket_member`)
- Sebelumnya: DSM check memblokir PIC yang belum punya kualifikasi DSM → PIC tidak bisa lihat tiketnya

---

## Checklist Implementasi Jarvies

```
TICKET TYPE
□ Tampilkan ticket_type di list dan detail tiket
□ Handle nilai null dengan "—" atau "Not set"
□ Gunakan warna badge yang konsisten dengan EcoSystem

PRIORITY
□ Handle ticket_priority = null (jangan default ke "Medium")
□ Tampilkan "Pending" atau "—" untuk null

TEAM DISPLAY
□ Di detail tiket: tampilkan Agent (PIC) dari employee.employee_name
□ Di detail tiket: tampilkan Members dari array members[]
□ Customer tidak bisa add/remove members (readonly display)

EMAIL RENDERING
□ Cek field message_html untuk pesan channel="email"
□ Render dengan iframe sandbox atau DOMPurify
□ Jangan tampilkan is_internal_note=true ke customer
□ Tampilkan cc_emails jika ada

STAGING / NULL HANDLING
□ Handle ticket_priority = null di semua tampilan
□ Handle ticket_type = null di semua tampilan
□ Setelah admin approve staging → refresh data tiket
```

---

---

## PERUBAHAN 6 — `ticket_number` Sekarang Nullable ✅ SUDAH DIJALANKAN

### Masalah
Kolom `ticket_number` di tabel `ticket` sebelumnya `NOT NULL UNIQUE`. Ini menyebabkan **DB error** jika Jarvies mencoba membuat tiket tanpa terlebih dahulu generate nomor tiket (karena logika generate nomor ada di EcoSystem).

### Solusi
Migration baru membuat `ticket_number` menjadi `nullable`:

```
Migration: 2026_02_26_081938_make_ticket_number_nullable_in_ticket_table.php
Status:    ✅ Sudah dijalankan
```

Perubahan pada database:
```sql
-- Sebelum
ticket_number VARCHAR(255) NOT NULL UNIQUE

-- Sekarang
ticket_number VARCHAR(255) NULL UNIQUE
```

> **Catatan MySQL:** UNIQUE index tetap berlaku untuk nilai non-null. MySQL memperbolehkan banyak baris `NULL` dalam kolom UNIQUE — jadi uniqueness untuk nomor tiket yang sudah di-assign tetap terjaga.

### Flow yang benar

```
Jarvies customer submit tiket
    ↓
staging_ticket dibuat (ticket_number = tidak ada di sini)
    ↓
Helpdesk EcoSystem approve staging
    ↓
ticket dibuat dengan ticket_number yang di-generate oleh EcoSystem
(format: YYMM-XXXX-0000)
```

> Jarvies **tidak perlu** generate `ticket_number` sendiri. Nomor diassign oleh EcoSystem.

### Yang perlu dilakukan Jarvies
- Saat membuat staging ticket → `ticket_number` tidak relevan (staging tidak punya nomor)
- Saat membaca ticket yang sudah approved → tampilkan `ticket_number` jika ada
- Handle `ticket_number = null` gracefully → tampilkan `"—"` atau `"Pending"` sebelum approved

---

## Referensi File EcoSystem

| Komponen | File |
|---|---|
| Ticket API (index, show, myTickets, addMember) | `app/Http/Controllers/TicketController.php` |
| Ticket view controller (pass data ke blade) | `app/Http/Controllers/TicketViewController.php` |
| Staging ticket API + approve/reject | `app/Http/Controllers/StagingTicketController.php` |
| Staging ticket service (business logic) | `app/Services/StagingTicketService.php` |
| Ticket message API | `app/Http/Controllers/TicketMessageController.php` |
| Email processor (fetch dari M365) | `app/Http/Controllers/EmailController.php` |
| Ticket model | `app/Models/Ticket.php` |
| TicketMessage model | `app/Models/TicketMessage.php` |
| API routes | `routes/api.php` |
| Web routes | `routes/web.php` |
| Migration ticket_type | `database/migrations/2026_02_25_200000_add_ticket_type_to_ticket.php` |
| Migration cc_emails | `database/migrations/2026_02_25_110415_add_cc_emails_to_ticket_message_and_staging.php` |

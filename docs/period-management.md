# Period Management

## Ringkasan

Period Management mengatur siklus hidup periode pelaporan (reporting period) yang menentukan kapan employee dapat mengajukan timesheet. Setiap periode memiliki status global dan dua status domain (Project dan Support) yang dikelola secara independen oleh role yang berbeda.

---

## Aturan Periode (21-20)

```
Periode bulan M mencakup tanggal 21 bulan (M-1) sampai 20 bulan M

Contoh:
  Periode April 2026  = 21 Maret 2026   → 20 April 2026
  Periode Januari 2026 = 21 Desember 2025 → 20 Januari 2026
```

**Penentuan periode dari tanggal:**
- Hari 1–20 bulan M → Periode M
- Hari 21–31 bulan M → Periode M+1

---

## Database Schema

### Tabel: `reporting_periods`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint, PK | Primary key |
| `year`, `month` | int | Identitas periode (unique constraint) |
| `start_date`, `end_date` | date | Rentang tanggal periode |
| `global_status` | string | `not_open` / `open` / `closed` |
| `opened_at`, `opened_by` | timestamp, FK | Kapan/siapa buka global |
| `project_status` | string | `not_open` / `open` / `closed` |
| `project_opened_at`, `project_opened_by` | timestamp, FK | — |
| `project_closed_at`, `project_closed_by` | timestamp, FK | — |
| `support_status` | string | `not_open` / `open` / `closed` |
| `support_opened_at`, `support_opened_by` | timestamp, FK | — |
| `support_closed_at`, `support_closed_by` | timestamp, FK | — |

### Tabel: `period_audit_logs`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint, PK | Primary key |
| `period_id` | FK | Periode terkait |
| `action` | string | Tipe aksi (lihat daftar di bawah) |
| `actor_id` | FK | Employee yang melakukan aksi |
| `actor_role_id` | int | Role saat aksi dilakukan |
| `is_force` | boolean | True jika force close |
| `metadata` | JSON | Data tambahan (misal: tanggal lama/baru) |
| `created_at` | timestamp | Waktu aksi |

### Tabel: `period_late_exception_requests`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint, PK | Primary key |
| `period_id` | FK | Periode yang diminta aksesnya |
| `employee_id` | FK | Employee pengaju |
| `domain` | string | `project` atau `support` |
| `notes` | text | Alasan pengajuan |
| `status` | string | `pending_head` / `pending_rpmo` / `approved` / `rejected` |
| `head_id` | FK, nullable | Head yang approve Level 1 |
| `head_approved_at` | timestamp, nullable | Waktu Head approve |
| `head_notes` | text, nullable | Catatan Head |
| `rpmo_id` | FK, nullable | RPMO yang approve Level 2 |
| `rpmo_approved_at` | timestamp, nullable | Waktu RPMO approve |
| `rpmo_notes` | text, nullable | Catatan RPMO |
| `expires_at` | timestamp, nullable | Batas waktu akses (wajib diisi saat approve Level 2) |
| `rejected_by` | FK, nullable | Yang menolak |
| `rejected_at` | timestamp, nullable | Waktu penolakan |
| `rejection_notes` | text, nullable | Alasan penolakan |

---

## Status Periode

### Global Status

| Status | Arti |
|---|---|
| `not_open` | Dibuat tapi belum dibuka RPMO. Tidak ada timesheet yang bisa disubmit. |
| `open` | Aktif. Timesheet bisa disubmit jika domain-nya juga open. |
| `closed` | Ditutup. Pengajuan timesheet diblokir (kecuali ada late exception). |

### Domain Status (Project / Support)

| Status | Arti |
|---|---|
| `not_open` | Menunggu Head buka domain. Employee domain ini belum bisa submit. |
| `open` | Domain aktif. Employee bisa submit jika global juga open. |
| `closed` | Domain ditutup. Pengajuan timesheet diblokir untuk domain ini. |

---

## Siklus Hidup Periode

```
1. RPMO buat periode          → global: not_open, project: not_open, support: not_open
       ↓
2. RPMO buka global           → global: open
       ↓
3. Heads buka domain masing-masing:
   - Head of Project          → project: open
   - Head of Support          → support: open
       ↓
4. Periode aktif              → semua employee bisa submit timesheet
       ↓
5. Heads tutup domain masing-masing (bisa tidak bersamaan):
   - Head of Project          → project: closed
   - Head of Support          → support: closed
       ↓
6. Syarat terpenuhi (kedua domain closed)
       ↓
7. RPMO tutup global          → global: closed
       ↓
8. Periode tersimpan di history (akses hanya via late exception)
```

**Aturan penting:**
- Hanya **satu** periode yang boleh globally open pada satu waktu
- Global baru bisa ditutup jika **kedua domain sudah closed**
- Domain baru bisa dibuka jika **global sudah open**
- RPMO bisa force-close domain tanpa menunggu Head

---

## Role & Permission

### PERIOD_MANAGEMENT_GROUP

Role yang bisa mengakses halaman Period Management:
- Admin (1), Head of Project (4), Head of Support (5), RPMO (7)

### Matriks Permission

| Aksi | RPMO | Admin | Head of Project | Head of Support | Lainnya |
|---|---|---|---|---|---|
| Buat periode | ✓ | ✓ | ✗ | ✗ | ✗ |
| Buka/tutup global | ✓ | ✓ | ✗ | ✗ | ✗ |
| Force close domain | ✓ | ✓ | ✗ | ✗ | ✗ |
| Edit tanggal periode | ✓ | ✓ | ✗ | ✗ | ✗ |
| Hapus periode | ✓ | ✓ | ✗ | ✗ | ✗ |
| Buka/tutup domain Project | ✓ | ✓ | ✓ | ✗ | ✗ |
| Buka/tutup domain Support | ✓ | ✓ | ✗ | ✓ | ✗ |
| Lihat audit log | ✓ | ✓ | ✓ | ✓ | ✗ |
| Lihat exception requests | ✓ | ✓ | ✓ (domain sendiri) | ✓ (domain sendiri) | ✗ |
| Approve exception L1 (Head) | ✗ | ✓ | ✓ (domain sendiri) | ✓ (domain sendiri) | ✗ |
| Approve exception L2 (RPMO) | ✓ | ✓ | ✗ | ✗ | ✗ |
| Submit exception request | ✗ | ✗ | ✗ | ✗ | ✓ (employee domain) |

---

## Validasi Timesheet (Period Gate)

**File:** `app/Services/PeriodService::canSubmitTimesheet()`

### Tier 1 — Bypass berdasarkan role

Employee dengan role berikut **tidak terkena period restriction**:
- Admin, RPMO, Helpdesk, Internship

### Tier 2 — Window aktif

Window valid = **periode saat ini + periode sebelumnya**

Jika tanggal timesheet berada dalam window:
- `global_status` harus `open`
- Domain status sesuai tipe timesheet harus `open`

Jika di luar window:
- Employee harus punya **Late Exception Request** yang approved dan belum expired

### Tier 3 — Resolusi domain

| Role Employee | Domain |
|---|---|
| Delivery Support User (2) | support |
| Delivery Project User (15) | project |

### Pesan Error

```
"Period not available. Waiting for RPMO to create and open this period."
"Period not yet opened. Waiting for RPMO to open this period."
"Period is closed."
"{Domain} domain has not been opened by {Domain} Head."
"{Domain} domain is closed."
"You can only submit timesheets for the current period or the previous period.
 For older periods, submit a Late Exception Request."
```

---

## Late Exception Request — Alur 2-Level Approval

### Status Flow

```
Employee ajukan request
         ↓
    [ pending_head ]
         ↓
   Head review
   ├── Approve → [ pending_rpmo ] → notif ke RPMO
   └── Reject  → [ rejected ] (END)
         ↓
   RPMO review + set expires_at (wajib)
   ├── Approve → [ approved ] → akses aktif sampai expires_at
   └── Reject  → [ rejected ] (END)
```

### Status Detail

| Status | Keterangan |
|---|---|
| `pending_head` | Menunggu review Head domain |
| `pending_rpmo` | Head sudah approve, menunggu RPMO |
| `approved` | Kedua level approve, akses aktif |
| `rejected` | Ditolak (bisa Head atau RPMO) |
| expired (display) | `approved` tapi `expires_at <= now()`, akses nonaktif |

### Logika Akses Aktif

```php
isAccessActive() = (status === 'approved') AND (expires_at > now())
```

Kedua kondisi harus terpenuhi. Jika salah satu gagal → akses tidak berlaku.

### Aturan Request

- **Satu request aktif per (period + employee)** — tidak bisa duplikat
- Employee bisa ajukan ulang hanya setelah ditolak atau expired
- Setelah RPMO approve, employee hanya bisa submit timesheet **sampai batas expires_at**
- `expires_at` **wajib diisi** saat RPMO approve — tidak bisa approve tanpa deadline

### Notifikasi yang Dikirim

| Event | Penerima | Tipe |
|---|---|---|
| Employee submit request | Head domain aktif | `late_exception_submitted` |
| Head approve (L1) | Employee pengaju | `late_exception_head_approved` |
| Head approve (L1) | Semua RPMO aktif | `late_exception_pending_rpmo` |
| Head reject | Employee pengaju | `late_exception_head_rejected` |
| RPMO approve (L2) | Employee pengaju | `late_exception_approved` |
| RPMO reject | Employee pengaju | `late_exception_rejected` |

---

## Audit Log

Setiap perubahan status periode dicatat secara otomatis.

### Daftar Aksi yang Dicatat

| Aksi | Keterangan | Warna |
|---|---|---|
| `period_created` | Periode baru dibuat | Biru |
| `global_open` | RPMO buka periode (pertama kali) | Hijau |
| `global_reopen` | RPMO buka ulang periode yang sudah closed | Hijau |
| `global_close` | RPMO tutup periode | Abu |
| `project_open` | Head of Project buka domain project | Hijau |
| `project_close` | Head of Project tutup domain project | Abu |
| `support_open` | Head of Support buka domain support | Hijau |
| `support_close` | Head of Support tutup domain support | Abu |
| `force_close_project` | RPMO force-close domain project | Merah |
| `force_close_support` | RPMO force-close domain support | Merah |
| `date_updated` | Tanggal periode diubah (metadata: lama/baru) | — |

Force close ditandai dengan `is_force = true` di database.

---

## API Endpoints

### Periode

| Method | URL | Siapa | Keterangan |
|---|---|---|---|
| `GET` | `/api/periods/active` | Semua | Periode yang sedang globally open |
| `GET` | `/api/periods/closed` | Semua | Daftar periode closed (untuk late exception) |
| `POST` | `/api/periods` | RPMO/Admin | Buat periode baru |
| `PATCH` | `/api/periods/{id}/dates` | RPMO/Admin | Edit tanggal periode |
| `DELETE` | `/api/periods/{id}` | RPMO/Admin | Hapus periode |
| `POST` | `/api/periods/{id}/open-global` | RPMO/Admin | Buka global |
| `POST` | `/api/periods/{id}/close-global` | RPMO/Admin | Tutup global (syarat: kedua domain closed) |
| `POST` | `/api/periods/{id}/force-close` | RPMO/Admin | Force close domain |
| `POST` | `/api/periods/{id}/open-domain` | Head/Admin | Buka domain |
| `POST` | `/api/periods/{id}/close-domain` | Head/Admin | Tutup domain |
| `GET` | `/api/periods/{id}/audit-logs` | Management Group | Riwayat aksi periode |

### Late Exception Request

| Method | URL | Siapa | Keterangan |
|---|---|---|---|
| `POST` | `/api/periods/exception-requests` | Employee | Ajukan request |
| `GET` | `/api/periods/my-exception-requests` | Employee | Lihat request sendiri |
| `GET` | `/api/periods/exception-requests` | Head/RPMO/Admin | Lihat semua pending request |
| `PATCH` | `/api/periods/{id}/exception-requests/{reqId}/head-decide` | Head/Admin | Approve/reject Level 1 |
| `PATCH` | `/api/periods/{id}/exception-requests/{reqId}/rpmo-decide` | RPMO/Admin | Approve/reject Level 2 |

### Web

| URL | Keterangan |
|---|---|
| `/rpmo/periods` | Halaman Period Management |

---

## Frontend

**File:** `resources/views/rpmo/periods/index.blade.php`

### Seksi Halaman

1. **Active Period Card** — Periode yang sedang open dengan:
   - Rentang tanggal
   - 3 status badge (Global, Project, Support)
   - Tombol aksi: Open/Close Global, Open/Close Domain, Force Close
   - Tombol Audit Log, Tombol Exception Requests

2. **Pending Period Card** — Jika tidak ada periode open, tampilkan periode pending berikutnya

3. **Period History Table** — Daftar semua periode (terbaru di atas) dengan status badge dan action menu per baris

### Modal yang Tersedia

| Modal | Fungsi |
|---|---|
| Create Period | Form buat periode baru (tahun, bulan, tanggal opsional) |
| Close Global | Konfirmasi tutup global |
| Close Domain | Konfirmasi tutup domain |
| Force Close | Konfirmasi force close (peringatan merah) |
| Audit Log | Tabel riwayat aksi dengan aktor dan waktu |
| Edit Dates | Form ubah tanggal periode (RPMO/Admin) |
| Delete Period | Konfirmasi hapus dengan warning |
| Exception Requests | Daftar pending request + form approve/reject per item |

### UI Exception Requests Queue

**Head melihat:** request domain sendiri dengan status `pending_head`
**RPMO melihat:** request dengan status `pending_rpmo`
**Admin melihat:** semua request semua status

Per kartu request:
- Nama employee, label periode, domain
- Catatan/alasan pengaju
- Status badge
- Jika approved: "Akses aktif sampai {tanggal}"
- Jika expired: "Akses expired {tanggal}" (abu)

**Formulir Head (L1):** Input catatan → Approve / Reject

**Formulir RPMO (L2):** Datetime picker deadline (default: 7 hari dari sekarang) + catatan → Approve & Set Deadline / Reject

---

## File Terkait

| File | Peran |
|---|---|
| `app/Http/Controllers/PeriodManagementController.php` | Controller utama semua endpoint periode |
| `app/Services/PeriodService.php` | Logic open/close/force-close, validasi timesheet |
| `app/Models/ReportingPeriod.php` | Model periode + helpers `periodFor()`, `dateRange()`, `isGloballyOpen()` |
| `app/Models/PeriodAuditLog.php` | Model audit log + label/warna per aksi |
| `app/Models/PeriodLateExceptionRequest.php` | Model request + `isAccessActive()`, `isExpired()` |
| `app/Enums/RoleId.php` | Konstanta `PERIOD_MANAGEMENT_GROUP` |
| `routes/web.php` | Route halaman period management |
| `routes/api.php` | Semua API route periode dan exception request |
| `resources/views/rpmo/periods/index.blade.php` | Halaman period management |
| `database/migrations/2026_04_21_000001_extend_reporting_periods_table.php` | Schema tabel periode |
| `database/migrations/2026_04_24_000001_create_period_late_exception_requests_table.php` | Schema tabel late exception |
| `database/migrations/2026_04_24_000002_add_expires_at_to_period_late_exception_requests.php` | Tambah kolom expires_at |

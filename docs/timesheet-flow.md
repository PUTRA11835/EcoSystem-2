# Timesheet Flow

## Ringkasan

Sistem timesheet EcoSystem mendukung tiga tipe pencatatan waktu kerja: **Project**, **Support**, dan **Office**. Setiap tipe memiliki field, aturan validasi, dan jalur approval yang berbeda. Pengajuan timesheet dibatasi oleh periode yang sedang aktif.

---

## Tipe Timesheet

| Tipe | Kondisi | Field Utama |
|---|---|---|
| **Project** | `delivery_projects_id` dan `activity_id` terisi | Proyek, aktivitas, jam, billable, lokasi |
| **Support** | `ticket_id` terisi | Ticket, MD consumed, on-site/remote |
| **Office** | Keduanya null | Jam kerja, lokasi, deskripsi |

### Project
- Harus terhubung ke aktivitas proyek yang sudah di-assign ke employee
- Bisa billable atau non-billable
- Mencatat jam kerja dan tipe aktivitas

### Support
- Terhubung ke ticket dukungan
- Tidak mencatat jam kerja (start/end time default 00:00)
- Mencatat **MD consumed** (mandays yang digunakan) terhadap quota yang disetujui
- MD quota bersumber dari `ConsultantMandaysDetail.mandays + approved_additional`

### Office
- Tidak terhubung ke proyek maupun ticket
- Mencatat jam kerja reguler untuk kegiatan internal

---

## Database Schema

### Tabel: `timesheets`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint, PK | Primary key |
| `employee_id` | FK | Pemilik timesheet |
| `delivery_projects_id` | FK, nullable | Proyek (project type) |
| `activity_id` | FK, nullable | Aktivitas proyek (project type) |
| `ticket_id` | FK, nullable | Ticket dukungan (support type) |
| `date` | DATE | Tanggal kerja |
| `start_time` | TIME | Jam mulai |
| `end_time` | TIME | Jam selesai |
| `duration_minutes` | INT | Dihitung otomatis dari start–end |
| `description` | TEXT | Deskripsi pekerjaan |
| `activity_type` | ENUM | `development`, `meeting`, `documentation`, `testing`, `support`, `training`, `other` |
| `presence` | VARCHAR, nullable | `onsite`, `remote`, `hybrid` |
| `location` | TEXT, nullable | Lokasi kerja |
| `status` | ENUM | `draft`, `submitted`, `approved`, `rejected` |
| `rejection_reason` | TEXT, nullable | Alasan penolakan |
| `is_billable` | BOOLEAN | Billable (project) |
| `md_consumed` | DECIMAL(8,2), nullable | Mandays digunakan (support) |
| `approved_by` | FK, nullable | Employee ID approver |
| `approved_at` | TIMESTAMP, nullable | Waktu approval |
| `period_year` | SMALLINT, nullable | Override tahun periode |
| `period_month` | TINYINT, nullable | Override bulan periode |
| `deleted_at` | TIMESTAMP | Soft delete |

---

## Status Flow

```
               [buat baru]
                   ↓
               [ draft ]
             ↙          ↘
      [submit]          [hapus]
          ↓
       [submitted]
       ↙       ↘
  [approve]   [reject]
      ↓           ↓
  [approved]  [rejected] → edit ulang → [draft] → [submitted] → ...
```

### Aturan transisi status

| Dari | Ke | Siapa |
|---|---|---|
| `draft` | `submitted` | Employee pemilik |
| `submitted` | `approved` | Approver sesuai tipe |
| `submitted` | `rejected` | Approver sesuai tipe |
| `rejected` | `draft` (re-edit) | Employee pemilik |
| `approved` | — | Tidak bisa diubah/dihapus |

---

## Alur Approval

### Siapa yang approve?

| Tipe Timesheet | Approver |
|---|---|
| Project | Head of Project (role 4) |
| Support | Head of Support (role 5) |
| Office | RPMO (role 7) |
| Semua | Admin (role 1) |

Deteksi tipe dilakukan dari field yang terisi:
- `delivery_projects_id` NOT NULL → Project → Head of Project
- `ticket_id` NOT NULL → Support → Head of Support
- Keduanya NULL → Office → RPMO

### Notifikasi saat submit

Saat employee submit timesheet, notifikasi tipe `timesheet_submitted` dikirim ke semua employee aktif dengan role approver yang sesuai.

```
Preview: "{Nama} submitted a [project/support/office] timesheet for approval"
```

---

## Aturan Periode (Period Gate)

Timesheet hanya bisa disubmit ke periode yang valid. Pengecekan dilakukan oleh `PeriodService::canSubmitTimesheet()`.

### Siapa yang dikecualikan dari pengecekan?

- Admin, RPMO, Helpdesk, Internship → **bebas period, selalu diizinkan**

### Window aktif

Employee domain (Support/Project) hanya bisa submit ke:
- **Periode saat ini** (periode yang mencakup hari ini)
- **Periode sebelumnya** (satu bulan ke belakang)

Di luar window ini, employee harus memiliki **Late Exception Request** yang disetujui dan belum kedaluwarsa.

### Penghitungan periode (aturan 21-20)

| Tanggal | Masuk periode |
|---|---|
| 1–20 bulan M | Periode M |
| 21–31 bulan M | Periode M+1 |

Contoh: tanggal 15 April → Periode April. Tanggal 25 April → Periode Mei.

---

## Mandays (MD) — Support Timesheet

### Sumber Quota

```
Quota = ConsultantMandaysDetail.mandays
      + ConsultantMandaysDetail.approved_additional
```

Diambil dari proposal mandays yang sudah `approved` (terbaru per ticket).

### Penghitungan Sisa

```
Consumed = SUM(md_consumed) WHERE ticket_id=X AND employee_id=Y
           AND status IN ('draft', 'submitted', 'approved')
           -- Timesheet rejected TIDAK dihitung (MD kembali ke quota)

Remaining = Quota - Consumed
```

### Endpoint

```
GET /api/timesheets/remaining-md?ticket_id={id}

Response:
{
  "quota": 5.0,
  "consumed": 1.5,
  "remaining": 3.5,
  "employee_id": 103
}
```

---

## API Endpoints

### CRUD

| Method | URL | Fungsi |
|---|---|---|
| `GET` | `/api/timesheets` | List timesheet (filtered by role) |
| `GET` | `/api/timesheets/{id}` | Detail satu timesheet |
| `POST` | `/api/timesheets` | Buat timesheet baru |
| `PUT` | `/api/timesheets/{id}` | Edit timesheet (hanya draft) |
| `DELETE` | `/api/timesheets/{id}` | Hapus timesheet (hanya draft) |

### Workflow

| Method | URL | Fungsi |
|---|---|---|
| `POST` | `/api/timesheets/{id}/submit` | Submit (draft → submitted) |
| `POST` | `/api/timesheets/{id}/approve` | Approve (submitted → approved) |
| `POST` | `/api/timesheets/{id}/reject` | Reject dengan alasan |

### Approval Mode (untuk Head)

| Method | URL | Keterangan |
|---|---|---|
| `GET` | `/api/timesheets/submitted-for-approval` | Daftar submitted/approved/rejected (filter by role) |

Query params: `start_date`, `end_date`, `status`, `type_filter` (support/project/office)

### Data Pendukung

| Method | URL | Fungsi |
|---|---|---|
| `GET` | `/api/timesheets/valid-periods` | Periode valid untuk employee |
| `GET` | `/api/timesheets/my-projects` | Proyek yang di-assign ke employee |
| `GET` | `/api/timesheets/my-activities/{projectId}` | Aktivitas proyek tertentu |
| `GET` | `/api/timesheets/remaining-md` | Sisa MD untuk ticket |
| `GET` | `/api/timesheets/statistics` | Statistik jam kerja |

---

## Validasi

### Field wajib (POST/PUT)

```
employee_id   : required, exists di tabel employee
date          : required, format tanggal valid
start_time    : required (HH:mm)
end_time      : required, setelah start_time
description   : required
activity_type : required, salah satu dari enum yang valid
```

### Aturan bisnis

- Jika `delivery_projects_id` diisi → `activity_id` wajib diisi
- Jika `ticket_id` diisi → `activity_id` otomatis null (support tidak pakai activity)
- Kerja overnight diizinkan (end_time < start_time → otomatis tambah 24 jam)
- Timesheet `approved` tidak bisa diedit atau dihapus
- Reject wajib menyertakan `rejection_reason`

---

## Role & Visibilitas

| Role | Lihat Milik Sendiri | Lihat Semua | Approve/Reject |
|---|---|---|---|
| Admin (1) | ✓ | ✓ semua tipe | ✓ semua |
| Support User (2) | ✓ | Support (read-only) | ✗ |
| Head of Project (4) | ✓ | Project | ✓ Project |
| Head of Support (5) | ✓ | Support | ✓ Support |
| RPMO (7) | ✓ | Office | ✓ Office |
| Project User (15) | ✓ | Project (read-only) | ✗ |

### Locked Type di UI

- Support User / Head of Support → hanya tab Support yang terlihat
- Project User → hanya tab Project yang terlihat
- Admin, RPMO → melihat semua tipe

---

## Frontend

**View:** `resources/views/calendar/timesheets.blade.php`
**JS:** `public/js/calendar-timesheets.js`

### Mode Employee

- Statistik: Draft, Submitted, Approved, Rejected
- Tab tipe: All, Project, Support, Office
- Filter: Periode (bulan/tahun), Status, Activity Type
- Aksi bulk: Submit, Edit, Hapus (untuk draft yang dipilih)
- Modal create/edit: field berubah sesuai tipe yang dipilih

### Mode Approval (untuk Head/RPMO)

- Statistik: Total, Pending Review, Approved, Rejected
- Tabel Support punya 13 kolom: Date, Month, Year, Name, Ticket, Description, Customer, Quota MD, Activity, MD Consumed, On-Site, Status, Actions
- Aksi bulk: Bulk Approve atau Bulk Reject (dengan modal konfirmasi + alasan)
- Aksi per baris: Approve / Reject

### Fungsi JS Kunci

| Fungsi | Keterangan |
|---|---|
| `loadTimesheets()` | Load data employee mode |
| `loadSubmittedTimesheets()` | Load data approval mode |
| `handleTimesheetTypeChange()` | Swap field form sesuai tipe |
| `onSupportTicketSelected(id)` | Auto-isi customer, quota, remaining MD |
| `initializeTimePickers()` | Setup jam, hitung durasi |
| `confirmApprove()` | POST approve |
| `confirmReject()` | POST reject dengan alasan |
| `renderTimesheetRows()` | Render baris tabel (3 varian) |

---

## File Terkait

| File | Peran |
|---|---|
| `app/Http/Controllers/TimesheetController.php` | Controller utama |
| `app/Services/PeriodService.php` | Validasi periode, period gate |
| `app/Models/Timesheet.php` | Model Eloquent |
| `app/Models/ConsultantMandays.php` | Proposal MD |
| `app/Models/ConsultantMandaysDetail.php` | Detail quota MD per employee |
| `routes/api.php` | Semua API route timesheet |
| `resources/views/calendar/timesheets.blade.php` | Halaman timesheet |
| `public/js/calendar-timesheets.js` | Logika frontend |

# Dokumentasi Mandays Proposal

Tanggal: 2026-03-16
Branch: `el_branch`

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Database Schema](#2-database-schema)
3. [Alur Customer Mandays](#3-alur-customer-mandays)
4. [Alur Internal Mandays (Consultant Mandays)](#4-alur-internal-mandays-consultant-mandays)
5. [API Endpoints](#5-api-endpoints)
6. [Model & Relasi](#6-model--relasi)
7. [Status State Machine](#7-status-state-machine)
8. [Perilaku Khusus (Business Rules)](#8-perilaku-khusus-business-rules)
9. [Frontend (show.blade.php)](#9-frontend-showbladephp)
10. [Migrasi Database](#10-migrasi-database)

---

## 1. Gambaran Umum

Sistem mandays proposal terbagi menjadi **dua alur terpisah**:

| Aspek | Customer Mandays | Internal Mandays |
|---|---|---|
| Tabel utama | `customer_mandays` | `consultant_mandays` |
| Pengirim | PIC (role_id = 2) | PIC (role_id = 2) |
| Penerima akhir | Customer (role_id = 3) | Head of Support (role_id = 5) |
| Melalui | Helpdesk (role_id = 6) → Chat | Langsung ke Head of Support |
| Format detail | Activity × Module × Mandays | Employee × Module × Mandays |
| Versioning | Ya — setiap siklus baru membuat versi baru | Tidak — satu record per tiket, selalu diupdate |
| Bisa buat baru setelah approved | Ya | Ya |

---

## 2. Database Schema

### 2.1 Tabel `customer_mandays`

```sql
CREATE TABLE customer_mandays (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id               VARCHAR/INT,
    version                 INT,                    -- versi proposal (1, 2, 3, ...)
    proposed_by_agent_id    INT,                    -- employee_id PIC yang membuat
    proposed_at             TIMESTAMP NULL,
    submitted_to_customer_at TIMESTAMP NULL,
    sent_to_chat_at         TIMESTAMP NULL,         -- waktu dikirim ke chat
    status                  ENUM(
                                'draft',            -- PIC sedang menyusun
                                'pending_helpdesk', -- sudah disubmit PIC, menunggu Helpdesk
                                'sent_to_chat',     -- Helpdesk kirim ke chat customer
                                'approved',         -- customer setuju
                                'canceled'          -- dibatalkan oleh Helpdesk
                            ) DEFAULT 'draft',
    customer_notes          TEXT NULL,
    rejection_reason        TEXT NULL,              -- alasan penolakan customer
    notes                   TEXT NULL,              -- catatan internal Helpdesk
    total_mandays           DECIMAL(10,2),
    created_at              TIMESTAMP,
    updated_at              TIMESTAMP
);
```

### 2.2 Tabel `customer_mandays_detail`

```sql
CREATE TABLE customer_mandays_detail (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_mandays_id BIGINT UNSIGNED,
    activity            VARCHAR(150) NULL,  -- baris aktivitas (misal: Analisa, Development)
    module              VARCHAR(100),        -- kolom modul (misal: FI, CO, MM)
    mandays             DECIMAL(10,2),
    notes               TEXT NULL,
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP
);
```

### 2.3 Tabel `consultant_mandays`

```sql
CREATE TABLE consultant_mandays (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id               VARCHAR/INT,
    proposed_by_agent_id    INT,                    -- employee_id PIC
    proposed_at             TIMESTAMP NULL,
    last_edited_at          TIMESTAMP NULL,
    status                  ENUM(
                                'draft',
                                'pending_approval', -- disubmit ke Head of Support
                                'approved',
                                'rejected',
                                'needs_revision'    -- ditolak Head, PIC harus revisi
                            ) DEFAULT 'draft',
    approved_by_head_id     INT NULL,               -- employee_id Head of Support yang approve
    approved_at             TIMESTAMP NULL,
    rejection_reason        TEXT NULL,
    helpdesk_notes          TEXT NULL,
    total_mandays           DECIMAL(10,2),
    created_at              TIMESTAMP,
    updated_at              TIMESTAMP
);
```

> **Catatan:** Unique constraint pada `ticket_id` telah dihapus (migrasi `2026_03_03`) agar satu tiket bisa memiliki beberapa record (misal: setelah reject dan resubmit). Namun untuk internal mandays, selalu yang digunakan adalah record **terbaru** (order by `created_at` desc).

### 2.4 Tabel `consultant_mandays_detail`

```sql
CREATE TABLE consultant_mandays_detail (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    consultant_mandays_id   BIGINT UNSIGNED,
    employee_id             INT,        -- karyawan yang mengerjakan modul tersebut
    module                  VARCHAR(100),
    mandays                 DECIMAL(10,2),
    notes                   TEXT NULL,
    created_at              TIMESTAMP,
    updated_at              TIMESTAMP
);
```

### 2.5 Kolom Tambahan pada Tabel `ticket`

```sql
-- Ditambahkan migrasi 2026_03_03
mandays_proposal_status ENUM(
    'none',
    'pic_draft',
    'pending_helpdesk',
    'sent_to_chat',
    'approved',
    'canceled'
) DEFAULT 'none'

-- Ditambahkan migrasi 2026_03_13
internal_mandays_status ENUM(
    'none',
    'draft',
    'pending_head',
    'approved',
    'rejected'
) DEFAULT 'none'
```

---

## 3. Alur Customer Mandays

### 3.1 Diagram Alur

```
PIC
 |
 |-- [1] Buat draft (Activity × Module matrix)
 |-- [2] Submit ke Helpdesk
 |
Helpdesk
 |-- [3] Review, edit detail jika perlu
 |-- [4a] Submit ke Chat → status: sent_to_chat
 |-- [4b] Approve langsung → status: approved
 |-- [4c] Cancel → status: canceled
 |
Chat (TicketMessage)
 |-- [5] Customer melihat tabel mandays di chat
 |
Helpdesk (setelah respon customer via chat)
 |-- [6a] Approve → ticket.man_days diupdate, status: approved
 |-- [6b] Cancel → status: canceled, PIC bisa buat proposal baru
```

### 3.2 Transisi Status `ticket.mandays_proposal_status`

| Dari | Ke | Trigger |
|---|---|---|
| `none` | `pic_draft` | PIC menyimpan draft pertama |
| `pic_draft` | `pending_helpdesk` | PIC submit ke Helpdesk |
| `pending_helpdesk` | `sent_to_chat` | Helpdesk kirim ke chat |
| `pending_helpdesk` | `approved` | Helpdesk approve langsung |
| `sent_to_chat` | `approved` | Helpdesk konfirmasi customer setuju |
| `sent_to_chat` | `canceled` | Helpdesk batalkan |
| `approved` | `pic_draft` | PIC buat proposal baru (versi berikutnya) |
| `canceled` | `pic_draft` | PIC buat proposal baru |

### 3.3 Transisi Status `customer_mandays.status`

| Status | Keterangan |
|---|---|
| `draft` | PIC sedang menyusun, belum dikirim |
| `pending_helpdesk` | Sudah disubmit PIC, menunggu review Helpdesk |
| `sent_to_chat` | Helpdesk sudah kirim ke chat customer |
| `approved` | Disetujui |
| `canceled` | Dibatalkan |

### 3.4 Versioning

Setiap kali PIC membuat proposal baru (setelah `canceled` atau `approved`), dibuat record baru dengan `version` +1 dari versi tertinggi yang ada. Versi lama tetap tersimpan di database.

```php
// Contoh logika versioning di saveCustomerDraft
$latestVersion = CustomerMandays::where('ticket_id', $ticketId)->max('version') ?? 0;
CustomerMandays::create([
    'version' => $latestVersion + 1,
    ...
]);
```

### 3.5 Matriks Detail

Detail customer_mandays disimpan sebagai matriks **Activity (baris) × Module (kolom)**:

| Activity | FI | CO | MM |
|---|---|---|---|
| Analisa | 2 | 1 | 0 |
| Development | 5 | 3 | 2 |
| Testing | 1 | 1 | 1 |

Setiap sel yang terisi menjadi satu record di `customer_mandays_detail`.

---

## 4. Alur Internal Mandays (Consultant Mandays)

### 4.1 Diagram Alur

```
PIC
 |
 |-- [1] Buka modal "Propose Internal Mandays"
 |        → Jika belum ada record: prefill dari customer_mandays yang approved (per modul)
 |        → Tabel menampilkan: Nama | Modul | Mandays (per orang per modul)
 |
 |-- [2] Isi mandays per anggota tim, tambah catatan opsional
 |-- [3] Save Draft (status: draft)
 |-- [4] Submit ke Head of Support (status: pending_approval / ticket: pending_head)
 |
Head of Support
 |-- [5] Lihat proposal di sidebar tiket (badge muncul saat pending_head/approved/rejected)
 |-- [6a] Approve → status: approved, catat approved_by_head_id dan approved_at
 |-- [6b] Reject → status: rejected (= needs_revision di UI: draft), tambah rejection_reason
 |
PIC (setelah reject atau approved)
 |-- [7] Bisa langsung buat/edit proposal baru dan submit ulang
```

### 4.2 Transisi Status `ticket.internal_mandays_status`

| Dari | Ke | Trigger |
|---|---|---|
| `none` | `draft` | PIC simpan draft |
| `draft` | `pending_head` | PIC submit ke Head of Support |
| `pending_head` | `approved` | Head of Support approve |
| `pending_head` | `rejected` | Head of Support reject |
| `rejected` | `draft` | PIC edit dan simpan ulang |
| `draft` | `pending_head` | PIC submit ulang |
| `approved` | `draft` | PIC buat proposal baru |

### 4.3 Mapping Status DB → UI

| Status di DB (`consultant_mandays.status`) | Status di UI (`internal_mandays_status`) |
|---|---|
| `draft` | `draft` |
| `pending_approval` | `pending_head` |
| `approved` | `approved` |
| `rejected` | `rejected` |
| `needs_revision` | `draft` |

### 4.4 Format Detail

Detail internal mandays disimpan per **Employee × Module** (tanpa kolom Activity):

| employee_id | module | mandays |
|---|---|---|
| 12 (Budi - PIC) | FI | 3.5 |
| 12 (Budi - PIC) | CO | 2.0 |
| 15 (Andi - Member) | MM | 4.0 |
| 7 (Sari - Past Member) | SD | 1.5 |

### 4.5 Daftar Anggota Tim

Daftar orang yang ditampilkan di form internal mandays:

- **PIC** — dari `ticket.employee_id`
- **Member aktif** — dari tabel `ticket_member` untuk tiket tersebut
- **Past Member** — dari record `consultant_mandays_detail` yang pernah tersimpan untuk tiket ini (employee_id yang tidak lagi ada di `ticket_member`)

Modul yang ditampilkan per orang berasal dari `employee_qualification` masing-masing.

### 4.6 Prefill dari Customer Mandays

Saat PIC pertama kali membuka modal internal mandays dan belum ada record `consultant_mandays` untuk tiket tersebut, sistem akan:

1. Mencari `customer_mandays` versi terbaru dengan status `approved`
2. Menjumlahkan mandays per modul dari detailnya
3. Memasukkan total per modul ke baris milik **orang pertama** dalam daftar yang memiliki modul tersebut (biasanya PIC)

---

## 5. API Endpoints

Semua endpoint berada di bawah prefix `/api/tickets` dengan middleware `auth`.

### 5.1 Shared / Utility

| Method | Endpoint | Controller | Keterangan |
|---|---|---|---|
| GET | `/{ticketId}/mandays/modules` | `getModules` | Daftar modul unik dari kualifikasi PIC + member |

### 5.2 Customer Mandays (PIC → Helpdesk → Chat → Customer)

| Method | Endpoint | Controller | Keterangan |
|---|---|---|---|
| GET | `/{ticketId}/mandays/pic-draft` | `getCustomerDraft` | Ambil draft/proposal terbaru untuk PIC |
| POST | `/{ticketId}/mandays/pic-draft` | `saveCustomerDraft` | Simpan/update draft PIC |
| POST | `/{ticketId}/mandays/pic-draft/submit` | `submitCustomerDraft` | Submit ke Helpdesk |
| GET | `/{ticketId}/mandays/hd-draft` | `getHelpdeskDraft` | Helpdesk ambil proposal untuk direview |
| PUT | `/{ticketId}/mandays/hd-draft` | `saveHelpdeskDraft` | Helpdesk edit detail |
| POST | `/{ticketId}/mandays/hd-draft/submit-chat` | `submitToChat` | Helpdesk kirim ke chat |
| POST | `/{ticketId}/mandays/hd-draft/approve` | `approveCustomerMandays` | Helpdesk approve |
| POST | `/{ticketId}/mandays/hd-draft/cancel` | `cancelCustomerMandays` | Helpdesk cancel |

**Body `saveCustomerDraft`:**
```json
{
  "details": [
    { "activity": "Analisa", "module": "FI", "mandays": 2.0 },
    { "activity": "Development", "module": "FI", "mandays": 5.0 }
  ]
}
```

**Response `getCustomerDraft`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "version": 1,
    "status": "draft",
    "total_mandays": 7.0,
    "details": [
      { "id": 1, "activity": "Analisa", "module": "FI", "mandays": 2.0 },
      { "id": 2, "activity": "Development", "module": "FI", "mandays": 5.0 }
    ]
  },
  "ticket_mandays_status": "pic_draft"
}
```

### 5.3 Internal Mandays (PIC → Head of Support)

| Method | Endpoint | Controller | Keterangan |
|---|---|---|---|
| GET | `/{ticketId}/mandays/internal` | `getInternalProposal` | Ambil proposal + people list |
| POST | `/{ticketId}/mandays/internal` | `saveInternalProposal` | Simpan/update draft internal |
| POST | `/{ticketId}/mandays/internal/submit` | `submitInternalProposal` | Submit ke Head of Support |
| POST | `/{ticketId}/mandays/internal/approve` | `approveInternalProposal` | Head of Support approve |
| POST | `/{ticketId}/mandays/internal/reject` | `rejectInternalProposal` | Head of Support reject |

**Body `saveInternalProposal`:**
```json
{
  "details": [
    { "employee_id": 12, "module": "FI", "mandays": 3.5 },
    { "employee_id": 15, "module": "MM", "mandays": 4.0 }
  ],
  "notes": "Catatan opsional untuk Head of Support"
}
```

**Body `rejectInternalProposal`:**
```json
{
  "rejection_reason": "Mandays terlalu tinggi untuk modul FI, harap direvisi."
}
```

**Response `getInternalProposal`:**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "status": "pending_head",
    "notes": "...",
    "rejection_reason": null,
    "total_mandays": 7.5,
    "proposed_by": "Budi Santoso",
    "approved_by_head": null,
    "approved_at": null,
    "details": [
      { "id": 10, "employee_id": 12, "employee_name": "Budi Santoso", "module": "FI", "mandays": 3.5 },
      { "id": 11, "employee_id": 15, "employee_name": "Andi Wijaya", "module": "MM", "mandays": 4.0 }
    ]
  },
  "prefill_data": null,
  "internal_mandays_status": "pending_head",
  "people": [
    { "employee_id": 12, "name": "Budi Santoso", "role": "PIC", "modules": ["FI", "CO"] },
    { "employee_id": 15, "name": "Andi Wijaya", "role": "Member", "modules": ["MM", "SD"] },
    { "employee_id": 7,  "name": "Sari Dewi",   "role": "Past Member", "modules": ["SD"] }
  ]
}
```

> Jika belum ada proposal dan ada customer_mandays yang approved, `prefill_data` berisi `{ "FI": 7.0, "MM": 4.0 }`.

### 5.4 Endpoint Lama (Legacy - consultant_mandays via Helpdesk)

Endpoint berikut masih ada di routes tetapi merupakan alur lama sebelum pemisahan flow:

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/{ticketId}/mandays/proposal` | PIC proposal lama |
| POST | `/{ticketId}/mandays/proposal` | PIC simpan proposal lama |
| POST | `/{ticketId}/mandays/proposal/submit` | PIC submit proposal lama |
| GET | `/{ticketId}/mandays/helpdesk-review` | Helpdesk review proposal lama |
| PUT | `/{ticketId}/mandays/helpdesk-review/details` | Helpdesk edit detail lama |
| POST | `/{ticketId}/mandays/helpdesk-review/approve` | Helpdesk approve → kirim ke customer |
| POST | `/{ticketId}/mandays/helpdesk-review/reject` | Helpdesk reject |
| POST | `/{ticketId}/mandays/helpdesk-review/edit-resend` | Edit & resend ke customer |
| POST | `/{ticketId}/mandays/helpdesk-review/request-reproposal` | Minta PIC isi ulang |

---

## 6. Model & Relasi

### 6.1 `CustomerMandays`

```
CustomerMandays
  ├── belongsTo  Ticket          (via ticket_id)
  ├── belongsTo  Employee        (via proposed_by_agent_id)  → PIC
  └── hasMany    CustomerMandaysDetail
```

**Scopes tersedia:** `draft()`, `pendingHelpdesk()`, `sentToChat()`, `approved()`, `canceled()`, `latestVersion()`

**Method helper:** `getMatrix()` — pivot detail ke array Activity → Module → Mandays

### 6.2 `CustomerMandaysDetail`

```
CustomerMandaysDetail
  └── belongsTo  CustomerMandays (via customer_mandays_id)
```

**Fields:** `activity`, `module`, `mandays`, `notes`

### 6.3 `ConsultantMandays`

```
ConsultantMandays
  ├── belongsTo  Ticket          (via ticket_id)
  ├── belongsTo  Employee        (via proposed_by_agent_id)  → PIC
  ├── belongsTo  Employee        (via approved_by_head_id)   → Head of Support
  └── hasMany    ConsultantMandaysDetail
```

**Scopes tersedia:** `draft()`, `pendingApproval()`, `approved()`, `rejected()`, `needsRevision()`, `latestPerTicket()`

### 6.4 `ConsultantMandaysDetail`

```
ConsultantMandaysDetail
  ├── belongsTo  ConsultantMandays (via consultant_mandays_id)
  └── belongsTo  Employee          (via employee_id)  → karyawan yang mengerjakan
```

**Fields:** `employee_id`, `module`, `mandays`, `notes`

---

## 7. Status State Machine

### 7.1 Customer Mandays — Lengkap

```
                    ┌─────────────┐
                    │    none     │ (ticket.mandays_proposal_status)
                    └──────┬──────┘
                           │ PIC save draft
                           ▼
                    ┌─────────────┐
             ┌──────│  pic_draft  │◄─────────────────────────────┐
             │      └──────┬──────┘                              │
             │             │ PIC submit                          │
             │             ▼                                     │
             │    ┌──────────────────┐                          │
             │    │ pending_helpdesk │                          │
             │    └────────┬─────────┘                          │
             │             │                                     │
             │    ┌────────▼────────┐  Helpdesk cancel          │
             │    │  sent_to_chat   │──────────────────►┌────────┴───────┐
             │    └────────┬────────┘                   │    canceled    │
             │             │                            └───────┬────────┘
             │    ┌────────▼────────┐  Helpdesk cancel          │
             │    │    approved     │                   PIC buat │ proposal baru
             │    └─────────────────┘                           │
             │                                                   │
             └──── (PIC buat proposal baru setelah approved) ───┘
```

### 7.2 Internal Mandays — Lengkap

```
                    ┌─────────────┐
                    │    none     │ (ticket.internal_mandays_status)
                    └──────┬──────┘
                           │ PIC save draft
                           ▼
                    ┌─────────────┐
             ┌──────│    draft    │◄──────────────────────────────┐
             │      └──────┬──────┘                               │
             │             │ PIC submit                           │
             │             ▼                                       │
             │    ┌──────────────────┐                           │
             │    │  pending_head    │                           │
             │    └────────┬─────────┘                           │
             │             │                                     │
             │    ┌────────▼────────┐                  ┌────────┴────────┐
             │    │    approved     │                  │    rejected     │
             │    └────────┬────────┘                  └────────┬────────┘
             │             │                                    │
             └─────────────┴────(PIC buat proposal baru)───────┘
```

---

## 8. Perilaku Khusus (Business Rules)

### 8.1 Customer Mandays: Proposal Baru Setelah Approved

PIC **diperbolehkan** membuat proposal customer mandays baru meskipun proposal sebelumnya sudah berstatus `approved`.

- Tombol di sidebar berubah label menjadi **"Submit New Proposal"**
- Form menjadi editable (tidak read-only) saat status `approved`
- Saat menyimpan, karena status bukan `draft`, controller akan membuat **versi baru**
- Versi lama (yang `approved`) tetap tersimpan di database

**Implementasi di `saveCustomerDraft`:**
```php
// Hanya blokir jika status BUKAN draft/canceled/approved
if ($existing && !in_array($existing->status, ['draft', 'canceled', 'approved'])) {
    return $this->error('Cannot edit when status is ' . $existing->status);
}
```

### 8.2 Internal Mandays: Proposal Baru Setelah Approved

PIC **diperbolehkan** membuat dan submit ulang internal mandays meskipun status sudah `approved`.

- Saat menyimpan setelah approved, record lama **dihapus detailnya** dan diisi ulang
- Status direset ke `draft` terlebih dahulu
- PIC harus submit ulang untuk dikirim kembali ke Head of Support

### 8.3 Internal Mandays: Updateable (Bukan Versioned)

Internal mandays **tidak memiliki versioning**. Selalu beroperasi pada satu record terbaru:

```php
$proposal = ConsultantMandays::where('ticket_id', $ticketId)
    ->orderBy('created_at', 'desc')
    ->first();
```

### 8.4 Prefill Internal dari Customer Mandays

Logika prefill saat pertama kali membuka modal internal mandays:

1. Cek apakah ada `consultant_mandays` untuk tiket ini → jika ya, tampilkan datanya
2. Jika tidak ada → cari `customer_mandays` terbaru dengan status `approved`
3. Jumlahkan mandays per modul dari detailnya
4. Di frontend: distribusikan total per modul ke **orang pertama** dalam daftar yang memiliki modul tersebut (biasanya PIC)

### 8.5 Modul yang Ditampilkan

Modul yang muncul di form internal mandays berasal dari `employee_qualification` masing-masing anggota. Hanya modul yang dimiliki seseorang yang akan muncul di baris miliknya.

### 8.6 Head of Support: Kapan Tombol Muncul

Di sidebar tiket sisi Head of Support, tombol untuk melihat proposal internal mandays muncul jika `ticket.internal_mandays_status` bernilai:
- `pending_head` — ada proposal yang menunggu review
- `approved` — untuk melihat ulang yang sudah disetujui
- `rejected` — untuk melihat yang sudah ditolak

---

## 9. Frontend (show.blade.php)

### 9.1 Kondisi Tampil per Role

| Role | Bagian yang Muncul |
|---|---|
| PIC (role_id = 2) | Tombol "Propose Mandays" + "Propose Internal Mandays" di sidebar; modal PIC customer mandays; modal PIC internal mandays |
| Helpdesk (role_id = 6) | Bagian review Helpdesk di sidebar; modal review Helpdesk customer mandays |
| Head of Support (role_id = 5) | Tombol di sidebar (jika ada proposal internal); modal Head of Support internal mandays |
| Customer (role_id = 3) | Lihat via pesan chat (TicketMessage), bukan di halaman ticket show |

### 9.2 Variabel JavaScript — Customer Mandays (PIC)

| Variabel | Tipe | Keterangan |
|---|---|---|
| `picCurrentStatus` | string\|null | Status proposal saat ini |
| `picReadOnly` | boolean | True jika form tidak bisa diedit |
| `picDraftData` | object\|null | Data proposal dari API |
| `picModules` | array | Daftar modul dari `/mandays/modules` |
| `picActivities` | array | Daftar aktivitas yang dikonfigurasi |

**`picReadOnly` = true** jika status adalah `pending_helpdesk` atau `sent_to_chat` (sedang dalam proses, tidak bisa diedit).

**`picReadOnly` = false** jika status adalah `draft`, `canceled`, `approved`, atau `null` (bisa diedit/buat baru).

### 9.3 Variabel JavaScript — Internal Mandays (PIC)

| Variabel | Tipe | Keterangan |
|---|---|---|
| `internalPicCurrentStatus` | string\|null | Status proposal internal saat ini |
| `internalPicReadOnly` | boolean | True hanya saat `pending_head` |
| `internalPicData` | object\|null | Data dari API |
| `internalPicPeople` | array | Daftar `{employee_id, name, role, modules}` dari API |

### 9.4 Fungsi JavaScript Utama

**Customer Mandays:**

| Fungsi | Keterangan |
|---|---|
| `openPicMandaysModal()` | Buka modal, load modules dan draft |
| `picLoadDraft()` | Fetch data dari API, render matriks |
| `picRenderMatrix(valueMap)` | Render tabel Activity × Module |
| `picGetPayload()` | Kumpulkan data dari form untuk dikirim ke API |
| `picSaveDraft()` | POST ke `/mandays/pic-draft` |
| `picSubmitDraft()` | POST ke `/mandays/pic-draft/submit` |
| `picUpdateSidebarBadge(status)` | Update badge dan label tombol di sidebar |

**Internal Mandays (PIC):**

| Fungsi | Keterangan |
|---|---|
| `openInternalMandaysModal()` | Buka modal, trigger `internalPicLoad()` |
| `internalPicLoad()` | Fetch data + people dari API, render rows |
| `internalPicRenderRows(valueMap)` | Render tabel Name \| Module \| Mandays per orang per modul |
| `internalPicGetPayload()` | Kumpulkan `{employee_id, module, mandays}` dari form |
| `internalPicSaveDraft()` | POST ke `/mandays/internal` |
| `internalPicSubmit()` | Save lalu POST ke `/mandays/internal/submit` |
| `internalPicUpdateSidebarBadge(status)` | Update badge dan label tombol di sidebar |

**Internal Mandays (Head of Support):**

| Fungsi | Keterangan |
|---|---|
| `openHeadInternalModal()` | Buka modal, fetch data proposal |
| `headInternalBuildTable(details)` | Render tabel Name \| Module \| Mandays (read-only) |
| `headInternalApprove()` | POST ke `/mandays/internal/approve` |
| `headInternalReject()` | POST ke `/mandays/internal/reject` dengan `rejection_reason` |

### 9.5 Label Tombol Sidebar Berdasarkan Status

**Customer Mandays (PIC):**

| Status | Label Tombol |
|---|---|
| null / none | Propose Mandays |
| `pic_draft` | Update Draft |
| `pending_helpdesk` | View Proposal (read-only) |
| `sent_to_chat` | View Proposal (read-only) |
| `approved` | Submit New Proposal |
| `canceled` | Propose Mandays |

**Internal Mandays (PIC):**

| Status | Label Tombol |
|---|---|
| null / none | Propose Internal Mandays |
| `draft` | Update Internal Draft |
| `pending_head` | View Internal Proposal (read-only) |
| `approved` | Update Internal Mandays |
| `rejected` | Revise Internal Mandays |

---

## 10. Migrasi Database

### Urutan Migrasi yang Relevan

| File Migrasi | Tanggal | Perubahan |
|---|---|---|
| `2026_03_03_000001_update_mandays_tables_for_pic_flow.php` | 2026-03-03 | Drop unique `ticket_id` di `consultant_mandays`; tambah `rejection_reason`, `helpdesk_notes`; extend enum status; tambah `mandays_proposal_status` di `ticket` |
| `2026_03_05_000001_update_customer_mandays_for_new_flow.php` | 2026-03-05 | Tambah kolom `activity` di `customer_mandays_detail`; tambah `notes`, `sent_to_chat_at` di `customer_mandays`; ubah status enum `customer_mandays`; ubah status enum `ticket.mandays_proposal_status` |
| `2026_03_13_000001_add_internal_mandays_status_to_ticket.php` | 2026-03-13 | Tambah kolom `internal_mandays_status` ENUM ke tabel `ticket` |

### Detail Migrasi `2026_03_13`

```php
$table->enum('internal_mandays_status', ['none', 'draft', 'pending_head', 'approved', 'rejected'])
    ->default('none')
    ->after('mandays_proposal_status');
```

---

## Ringkasan Perubahan Terbaru

### Customer Mandays
- PIC bisa membuat proposal baru meskipun proposal sebelumnya sudah `approved`
- Guard di `saveCustomerDraft` tidak lagi memblokir status `approved`
- UI: tombol berubah label menjadi "Submit New Proposal" saat status `approved`
- Form tetap editable saat status `approved`

### Internal Mandays (Consultant Mandays)
- Fitur baru: PIC bisa mengajukan mandays internal langsung ke Head of Support
- Tidak melewati Helpdesk, tidak terkirim ke chat
- Tabel proposal menampilkan **Nama | Modul | Mandays** per orang (satu baris per employee per modul)
- Daftar orang mencakup PIC, member aktif, dan past member (dari riwayat `consultant_mandays_detail`)
- `employee_id` disimpan per baris detail; nama karyawan di-render dari relasi `employee.basicData`
- Head of Support melihat tombol di sidebar tiket dan bisa approve/reject
- Kolom `internal_mandays_status` ditambahkan di tabel `ticket`
- Menggunakan tabel `consultant_mandays` dan `consultant_mandays_detail` yang sudah ada
- Backend mengembalikan `people` array: `[{employee_id, name, role, modules}]` di response `GET /mandays/internal`

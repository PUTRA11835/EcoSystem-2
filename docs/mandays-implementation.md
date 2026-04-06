# Mandays Implementation — EcoSystem

> Dokumentasi implementasi fitur **Man Days Proposal** di EcoSystem (sisi karyawan).
> Dibuat: 2026-03-17

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Struktur Database](#2-struktur-database)
3. [Alur Lengkap](#3-alur-lengkap)
4. [API Endpoints](#4-api-endpoints)
5. [Response Format](#5-response-format)
6. [Status State Machine](#6-status-state-machine)
7. [Panduan Adaptasi ke Jarvies (Customer Side)](#7-panduan-adaptasi-ke-jarvies-customer-side)

---

## 1. Gambaran Umum

Sistem mandays terbagi menjadi **dua jalur terpisah**:

| Jalur | Aktor | Tujuan |
|---|---|---|
| **Customer Mandays** | PIC → Helpdesk → Customer | Proposal biaya ke customer |
| **Internal Mandays** | PIC → Head of Support | Distribusi kerja internal antar konsultan |

Kedua jalur berjalan independen dan masing-masing memiliki status sendiri di tabel `ticket`.

---

## 2. Struktur Database

### Kolom tambahan di tabel `ticket`

```sql
mandays_proposal_status ENUM(
  'none',             -- belum ada proposal
  'pic_draft',        -- PIC sedang buat draft
  'pending_helpdesk', -- PIC submit ke Helpdesk
  'sent_to_chat',     -- Helpdesk kirim ke customer via email
  'approved',         -- Helpdesk approve
  'canceled'          -- Helpdesk cancel
) DEFAULT 'none'

internal_mandays_status ENUM(
  'none',             -- belum ada proposal
  'draft',            -- PIC sedang buat draft
  'pending_head',     -- PIC submit ke Head of Support
  'approved',         -- Head of Support approve
  'rejected'          -- Head of Support reject (kembali ke PIC untuk revisi)
) DEFAULT 'none'
```

### Tabel `customer_mandays`

```sql
id                      BIGINT PK
ticket_id               VARCHAR (FK ke ticket.ticket_id)
version                 INT              -- auto-increment per ticket saat approved/canceled
proposed_by_agent_id    INT (FK employee)
proposed_at             DATETIME
submitted_to_customer_at DATETIME NULL   -- saat PIC submit ke Helpdesk
sent_to_chat_at         DATETIME NULL    -- saat Helpdesk kirim ke customer
status                  ENUM('draft','pending_helpdesk','sent_to_chat','approved','canceled')
customer_notes          TEXT NULL
rejection_reason        TEXT NULL
notes                   TEXT NULL        -- catatan Helpdesk (opsional)
total_mandays           DECIMAL(8,2)
created_at, updated_at
```

### Tabel `customer_mandays_detail`

```sql
id                   BIGINT PK
customer_mandays_id  BIGINT (FK customer_mandays.id)
activity             VARCHAR(150) NULL  -- nama aktivitas (misal: "Analisa", "CR")
module               VARCHAR(100)       -- nama modul SAP (FI, CO, MM, dll.)
mandays              DECIMAL(6,2)
notes                TEXT NULL
created_at, updated_at
```

### Tabel `consultant_mandays`

```sql
id                      BIGINT PK
ticket_id               VARCHAR (FK ke ticket.ticket_id)
proposed_by_agent_id    INT (FK employee)
proposed_at             DATETIME NULL
last_edited_at          DATETIME NULL
status                  ENUM('draft','pending_approval','approved','rejected','needs_revision')
approved_by_head_id     INT NULL (FK employee)
approved_at             DATETIME NULL
rejection_reason        TEXT NULL
helpdesk_notes          TEXT NULL       -- catatan PIC
total_mandays           DECIMAL(8,2)
created_at, updated_at
```

> **Catatan:** `consultant_mandays` tidak diversi — selalu update record yang sama (tidak seperti `customer_mandays` yang membuat versi baru setelah approved/canceled).

### Tabel `consultant_mandays_detail`

```sql
id                     BIGINT PK
consultant_mandays_id  BIGINT (FK consultant_mandays.id)
employee_id            INT (FK employee)
module                 VARCHAR(100)
mandays                DECIMAL(6,2)
notes                  TEXT NULL
created_at, updated_at
```

---

## 3. Alur Lengkap

### 3A. Customer Mandays

```
PIC                    Helpdesk                   Customer
 │                        │                           │
 │── POST pic-draft ──────►│                           │
 │   (simpan draft)        │                           │
 │                        │                           │
 │── POST pic-draft/submit─►│                           │
 │   status: pending_helpdesk                          │
 │                        │                           │
 │                        │── POST hd-draft/submit-chat─►│
 │                        │   status: sent_to_chat    │
 │                        │   (kirim email tabel MD)  │
 │                        │                           │
 │                        │◄── [customer approve via Jarvies]
 │                        │                           │
 │                        │── POST hd-draft/approve   │
 │                        │   status: approved        │
 │                        │   ticket.man_days diupdate│
```

**Catatan versioning:**
- Jika proposal sudah `approved` atau `canceled`, pembuatan draft baru akan otomatis membuat **versi baru** (`version + 1`).
- Versi lama tetap tersimpan di DB (history).

### 3B. Internal Mandays (Consultant)

```
PIC                    Head of Support
 │                          │
 │── POST internal ─────────►│
 │   (simpan draft)          │
 │                          │
 │── POST internal/submit ───►│
 │   status: pending_head    │
 │                          │
 │                          │── POST internal/approve
 │                          │   status: approved
 │                          │
 │                          │── POST internal/reject
 │◄─── (butuh revisi) ───────│
 │   status: rejected        │
 │   PIC edit & submit ulang │
```

---

## 4. API Endpoints

Base URL: `/api/tickets/{ticketId}/mandays/`

Semua endpoint memerlukan **session login** (middleware `web`).

### 4A. Shared

| Method | Path | Controller | Keterangan |
|---|---|---|---|
| GET | `modules` | `getModules` | Daftar modul dari kualifikasi PIC + members |

**Response `GET modules`:**
```json
{
  "success": true,
  "data": ["CO", "FI", "MM", "SD"]
}
```

---

### 4B. Customer Mandays — PIC

| Method | Path | Controller | Keterangan |
|---|---|---|---|
| GET | `pic-draft` | `getCustomerDraft` | Ambil draft/proposal terbaru |
| POST | `pic-draft` | `saveCustomerDraft` | Simpan atau update draft |
| POST | `pic-draft/submit` | `submitCustomerDraft` | Submit ke Helpdesk |

**Request Body `POST pic-draft`:**
```json
{
  "details": [
    {
      "activity": "Analisa",
      "module": "FI",
      "mandays": 2.0
    },
    {
      "activity": "CR",
      "module": "CO",
      "mandays": 1.5
    }
  ]
}
```

- `activity`: opsional, default tampil sebagai "General" jika null
- `module`: wajib, nama modul (ambil dari endpoint `modules`)
- `mandays`: wajib, angka > 0

**Aturan edit:**
- Bisa edit selama status `draft`
- **Tidak bisa edit** saat status `pending_helpdesk` atau `sent_to_chat`
- Setelah `approved`/`canceled` → membuat **versi baru**

---

### 4C. Customer Mandays — Helpdesk

| Method | Path | Controller | Keterangan |
|---|---|---|---|
| GET | `hd-draft` | `getHelpdeskDraft` | Ambil proposal untuk review |
| PUT | `hd-draft` | `saveHelpdeskDraft` | Edit detail + tambah notes |
| POST | `hd-draft/submit-chat` | `submitToChat` | Kirim ke customer via email |
| POST | `hd-draft/approve` | `approveCustomerMandays` | Approve → update `man_days` ticket |
| POST | `hd-draft/cancel` | `cancelCustomerMandays` | Cancel proposal |

**Request Body `PUT hd-draft`:**
```json
{
  "details": [...],
  "notes": "Catatan dari Helpdesk (opsional)"
}
```

**Efek `POST hd-draft/submit-chat`:**
1. Status diubah ke `sent_to_chat`
2. Email dikirim ke customer via Microsoft Graph API berisi tabel mandays (Activity × Module)
3. TicketMessage dibuat di thread ticket
4. `ticket.last_message_at` dan `ticket.last_agent_reply_at` diupdate

**Efek `POST hd-draft/approve`:**
- Status menjadi `approved`
- `ticket.man_days` diisi dengan `total_mandays` dari proposal

---

### 4D. Internal Mandays — PIC

| Method | Path | Controller | Keterangan |
|---|---|---|---|
| GET | `internal` | `getInternalProposal` | Ambil proposal + daftar orang |
| POST | `internal` | `saveInternalProposal` | Simpan draft |
| POST | `internal/submit` | `submitInternalProposal` | Submit ke Head of Support |

**Request Body `POST internal`:**
```json
{
  "details": [
    {
      "employee_id": 12,
      "module": "FI",
      "mandays": 3.0
    },
    {
      "employee_id": 15,
      "module": "CO",
      "mandays": 2.0
    }
  ],
  "notes": "Catatan PIC (opsional)"
}
```

**Response `GET internal`** (tambahan field `people` dan `prefill_data`):
```json
{
  "success": true,
  "data": { ... },
  "internal_mandays_status": "draft",
  "people": [
    {
      "employee_id": 12,
      "name": "Budi Santoso",
      "role": "PIC",
      "modules": ["FI", "CO"]
    },
    {
      "employee_id": 15,
      "name": "Siti Rahayu",
      "role": "Member",
      "modules": ["CO", "MM"]
    }
  ],
  "prefill_data": {
    "FI": 3.0,
    "CO": 2.0
  }
}
```

- `people`: PIC + active members + past members (dari history `consultant_mandays_detail`)
- `prefill_data`: hanya ada jika belum ada proposal internal tetapi customer mandays sudah `approved` — berisi total mandays per modul dari customer proposal sebagai referensi

---

### 4E. Internal Mandays — Head of Support

| Method | Path | Controller | Keterangan |
|---|---|---|---|
| POST | `internal/approve` | `approveInternalProposal` | Approve proposal |
| POST | `internal/reject` | `rejectInternalProposal` | Reject + alasan |

**Request Body `POST internal/reject`:**
```json
{
  "rejection_reason": "Alasan penolakan (wajib)"
}
```

---

## 5. Response Format

### Format Proposal Customer Mandays

```json
{
  "id": 1,
  "version": 1,
  "status": "pending_helpdesk",
  "total_mandays": 5.0,
  "notes": null,
  "rejection_reason": null,
  "proposed_at": "2026-03-17T09:00:00.000000Z",
  "details": [
    {
      "id": 1,
      "activity": "Analisa",
      "module": "FI",
      "mandays": 2.0
    },
    {
      "id": 2,
      "activity": "Analisa",
      "module": "CO",
      "mandays": 1.5
    }
  ]
}
```

### Format Proposal Internal Mandays

```json
{
  "id": 1,
  "status": "pending_approval",
  "notes": "Catatan PIC",
  "rejection_reason": null,
  "total_mandays": 5.0,
  "proposed_by": "Budi Santoso",
  "approved_by_head": null,
  "approved_at": null,
  "details": [
    {
      "id": 1,
      "employee_id": 12,
      "employee_name": "Budi Santoso",
      "module": "FI",
      "mandays": 3.0
    }
  ]
}
```

### Response saat Create/Update

```json
{
  "success": true,
  "message": "Draft saved.",
  "data": { ... },
  "ticket_mandays_status": "pic_draft"
}
```

Field `ticket_mandays_status` dan `internal_mandays_status` selalu disertakan di response agar frontend bisa update badge sidebar tanpa reload.

---

## 6. Status State Machine

### Customer Mandays (`ticket.mandays_proposal_status`)

```
none
  │
  ▼ POST pic-draft
pic_draft
  │
  ▼ POST pic-draft/submit
pending_helpdesk
  │
  ├──▶ PUT hd-draft          (Helpdesk edit — status tetap pending_helpdesk)
  │
  ├──▶ POST hd-draft/submit-chat
  │         sent_to_chat
  │           │
  │           └──▶ POST hd-draft/approve ──▶ approved
  │
  ├──▶ POST hd-draft/approve ──▶ approved
  │         (ticket.man_days diisi otomatis)
  │
  └──▶ POST hd-draft/cancel  ──▶ canceled
                                    │
                                    └──▶ POST pic-draft (versi baru)
```

### Internal Mandays (`ticket.internal_mandays_status`)

```
none
  │
  ▼ POST internal
draft
  │
  ▼ POST internal/submit
pending_head
  │
  ├──▶ POST internal/approve ──▶ approved
  │
  └──▶ POST internal/reject  ──▶ rejected
                                    │
                                    └──▶ POST internal (edit draft, status balik ke draft)
                                         POST internal/submit (submit ulang)
```

---

## 7. Panduan Adaptasi ke Jarvies (Customer Side)

Jarvies **hanya perlu membaca** proposal yang sudah sampai ke customer (`sent_to_chat`). Tidak ada action create/edit dari customer side — customer hanya melihat proposal dan memberikan respons.

### Endpoint yang dibutuhkan Jarvies

Karena Jarvies mengakses via **API Key** (bukan session), semua endpoint Jarvies ada di:
`/api/jarvies/tickets/{ticketId}/mandays/...`

> **Belum diimplementasi** — perlu ditambahkan di `routes/api.php` di group `jarvies.api_key`.

### Endpoint yang perlu dibuat untuk Jarvies

| Method | Path | Keterangan |
|---|---|---|
| GET | `/api/jarvies/tickets/{ticketId}/mandays/customer` | Ambil proposal aktif yang sudah `sent_to_chat` |
| POST | `/api/jarvies/tickets/{ticketId}/mandays/customer/approve` | Customer approve proposal |
| POST | `/api/jarvies/tickets/{ticketId}/mandays/customer/reject` | Customer reject + alasan |

### Logic Endpoint `GET customer`

Hanya tampilkan proposal jika status adalah `sent_to_chat`:

```php
$proposal = CustomerMandays::where('ticket_id', $ticketId)
    ->where('status', 'sent_to_chat')   // hanya yang sudah sampai ke customer
    ->latestVersion()
    ->with('details')
    ->first();
```

### Logic Endpoint `POST customer/approve`

```php
// Dari sisi Jarvies, customer menyetujui proposal
$proposal->update(['status' => 'approved']);
$ticket->update([
    'mandays_proposal_status' => 'approved',
    'man_days' => $proposal->total_mandays,
]);
```

### Logic Endpoint `POST customer/reject`

```php
// Customer reject → kembali ke Helpdesk untuk revisi
$proposal->update([
    'status' => 'pending_helpdesk',
    'rejection_reason' => $request->reason,
]);
$ticket->update(['mandays_proposal_status' => 'pending_helpdesk']);
```

### Tampilan di Jarvies

Tampilkan tabel Activity × Module yang sama seperti email yang diterima customer:

```
| Activity      | FI  | CO  | MM  |
|---------------|-----|-----|-----|
| Analisa       | 2.0 | 1.5 |  -  |
| CR            |  -  | 1.0 | 1.0 |
| Total         | 2.0 | 2.5 | 1.0 |

Total Man Days: 5.5
```

### Kapan tombol Approve/Reject muncul di Jarvies

Hanya tampilkan tombol jika `mandays_proposal_status === 'sent_to_chat'`.

```javascript
// Contoh kondisi di Jarvies
if (ticket.mandays_proposal_status === 'sent_to_chat') {
    // tampilkan tombol Approve dan Reject
}
```

### Status yang perlu dihandle di Jarvies UI

| `mandays_proposal_status` | Tampilan di Jarvies |
|---|---|
| `none` | Tidak tampilkan section mandays |
| `pic_draft` | Tidak tampilkan (masih internal) |
| `pending_helpdesk` | Tidak tampilkan (masih di Helpdesk) |
| `sent_to_chat` | **Tampilkan proposal + tombol Approve/Reject** |
| `approved` | Tampilkan proposal + badge "Approved" (read-only) |
| `canceled` | Tampilkan pesan "Proposal was canceled" |

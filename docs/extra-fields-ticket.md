# Tambahan Field Pada Create Ticket — Panduan EcoSystem

> **Tanggal:** 2026-03-11
> **Berlaku untuk:** Tim EcoSystem agar dapat menyesuaikan staging ticket dan form create ticket dengan field baru

---

## Field Baru yang Ditambahkan

Form create ticket di JARVIES sekarang memiliki 4 field tambahan (semua opsional, `varchar(255) NULL`):

| Field    | Tipe        | Keterangan                            |
|----------|-------------|---------------------------------------|
| `name`   | varchar(255)| Nama contact person pelapor           |
| `no_hp`  | varchar(255)| Nomor handphone pelapor               |
| `module` | varchar(255)| Modul/fitur terkait (freetext)        |
| `client` | varchar(255)| Nama client terkait (freetext)        |

---

## Perubahan di JARVIES (sudah diterapkan)

### 1. Migration
File: `database/migrations/2026_03_11_000001_add_extra_fields_to_staging_tickets.php`

Menambahkan 4 kolom baru ke tabel `staging_tickets`:
```sql
ALTER TABLE staging_tickets
  ADD COLUMN name   VARCHAR(255) NULL AFTER cc_emails,
  ADD COLUMN no_hp  VARCHAR(255) NULL AFTER name,
  ADD COLUMN module VARCHAR(255) NULL AFTER no_hp,
  ADD COLUMN client VARCHAR(255) NULL AFTER module;
```

### 2. Model `StagingTicket`
Ditambahkan ke `$fillable`: `name`, `no_hp`, `module`, `client`

### 3. Form `create.blade.php`
Ditambahkan 2 baris input (grid 2 kolom) di antara Subject dan Priority:
- Baris 1: **Name** + **No HP**
- Baris 2: **Module** + **Client**

### 4. `TicketController@store()` — 3 jalur pengiriman

#### Jalur A: Customer dengan OAuth Email
Field tambahan **disertakan di body email** dengan format:
```
{isi pesan customer}

--- Informasi Tambahan ---
Name   : Budi Santoso
No HP  : 081234567890
Module : Finance
Client : PT ABC

--
Budi Santoso
```
EcoSystem membaca email ini melalui `processInbox()` → staging ticket dibuat dari email.
Field tambahan **tidak otomatis tersimpan** ke kolom DB — perlu EcoSystem parsing (opsional).

#### Jalur B: Customer tanpa OAuth (API call ke EcoSystem)
Field tambahan dikirim sebagai JSON ke endpoint `POST /api/staging-tickets`:

```json
{
  "description":        "Subject tiket",
  "body":               "<p>Isi pesan...</p>",
  "ticket_priority":    "Medium",
  "sender_name":        "Budi Santoso",
  "submitted_by_email": "budi@example.com",
  "cc_emails":          "[\"cc@example.com\"]",
  "customer_id":        42,
  "name":               "Budi Santoso",
  "no_hp":              "081234567890",
  "module":             "Finance",
  "client":             "PT ABC"
}
```

#### Jalur C: Fallback lokal (EcoSystem API gagal)
JARVIES langsung menulis ke `staging_tickets` via `StagingTicketService::createFromWeb()`.
Field tersimpan langsung ke kolom DB.

---

## Yang Perlu Dilakukan EcoSystem

### 1. Jalankan Migration (jika belum)
Pastikan 4 kolom baru sudah ada di tabel `staging_tickets`:
```sql
-- Cek apakah kolom sudah ada:
SHOW COLUMNS FROM staging_tickets LIKE 'name';
SHOW COLUMNS FROM staging_tickets LIKE 'no_hp';
SHOW COLUMNS FROM staging_tickets LIKE 'module';
SHOW COLUMNS FROM staging_tickets LIKE 'client';
```

Jika belum ada, jalankan SQL:
```sql
ALTER TABLE staging_tickets
  ADD COLUMN IF NOT EXISTS name   VARCHAR(255) NULL AFTER cc_emails,
  ADD COLUMN IF NOT EXISTS no_hp  VARCHAR(255) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS module VARCHAR(255) NULL AFTER no_hp,
  ADD COLUMN IF NOT EXISTS client VARCHAR(255) NULL AFTER module;
```

### 2. Update Model `StagingTicket` di EcoSystem
Tambahkan ke `$fillable`:
```php
protected $fillable = [
    // ... existing fields ...
    'name',
    'no_hp',
    'module',
    'client',
];
```

### 3. Update Endpoint `POST /api/staging-tickets`
Endpoint ini menerima request dari JARVIES. Tambahkan penerimaan field baru:

```php
// Di StagingTicketController atau handler endpoint
$staging = StagingTicket::create([
    // ... existing fields ...
    'name'   => $request->input('name'),
    'no_hp'  => $request->input('no_hp'),
    'module' => $request->input('module'),
    'client' => $request->input('client'),
]);
```

### 4. (Opsional) Tampilkan di EcoSystem UI
Di halaman detail staging ticket (saat admin mereview), tampilkan field tambahan:

```html
<!-- Contoh tampilan di EcoSystem -->
<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="text-xs text-gray-500">Name</label>
        <p class="text-sm font-medium">{{ $staging->name ?? '-' }}</p>
    </div>
    <div>
        <label class="text-xs text-gray-500">No HP</label>
        <p class="text-sm font-medium">{{ $staging->no_hp ?? '-' }}</p>
    </div>
    <div>
        <label class="text-xs text-gray-500">Module</label>
        <p class="text-sm font-medium">{{ $staging->module ?? '-' }}</p>
    </div>
    <div>
        <label class="text-xs text-gray-500">Client</label>
        <p class="text-sm font-medium">{{ $staging->client ?? '-' }}</p>
    </div>
</div>
```

### 5. (Opsional) Saat Approve Staging → Salin ke Ticket
Jika field ini relevan untuk ditampilkan di ticket, tambahkan kolom yang sama ke tabel `tickets` dan salin nilainya saat proses approve.

Atau, tampilkan data dari `staging_tickets` sebagai referensi di detail ticket.

---

## Ringkasan Flow Lengkap

```
Customer isi form JARVIES (Subject, Name, No HP, Module, Client, Priority, CC, Details)
    │
    ├─ [OAuth] Kirim email ke helpdesk (field tambahan di body email)
    │       → EcoSystem processInbox() → buat staging dari email
    │       → Field tambahan: perlu parsing manual dari body email (opsional)
    │
    └─ [Non-OAuth] POST /api/staging-tickets (field tambahan di JSON payload)
            → EcoSystem simpan ke staging_tickets dengan semua field
            → Admin review staging → approve → buat ticket
```

---

## Catatan

- Semua field tambahan bersifat **opsional** (`nullable`) — tidak ada validasi required di JARVIES maupun EcoSystem
- Field `name` di sini adalah nama contact person, **berbeda dari `sender_name`** (nama customer yang login)
- Tidak ada perubahan pada struktur `tickets` — field ini hanya di `staging_tickets`

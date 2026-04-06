# Dokumentasi Perubahan Sistem EcoSystem

> **Tanggal:** 23 Februari 2026
> **Project:** EcoSystem — PT Eclectic Consulting Yogyakarta
> **Stack:** Laravel 11 + PHP + Blade + Tailwind CSS + MySQL

---

## Daftar Perubahan

| # | Perubahan | File Terdampak | Tipe |
|---|---|---|---|
| 1 | Fix JS SyntaxError `querySelector('#')` di dashboard | `dashboard.blade.php` | Bugfix |
| 2 | Fix tombol "My Profile" tidak mengarah ke halaman profile | `dashboard.blade.php` | Bugfix |
| 3 | Redesign halaman My Profile (layout lebih besar & rapi) | `profile/edit.blade.php` | Enhancement |
| 4 | Hapus banner merah di header halaman Profile | `profile/edit.blade.php` | Enhancement |
| 5 | Sistem Staging Ticket (validasi tiket dari customer) | Migration, Model, Service, Controller, View, Routes | Feature |

---

## Detail Perubahan

---

### 1. Fix JS SyntaxError `querySelector('#')` di Dashboard

**File:** `resources/views/dashboard.blade.php` (baris ~626)

**Masalah:**
Smooth scroll JavaScript menggunakan `document.querySelector(href)` di mana `href` bisa berisi
nilai `"#"` (anchor kosong). `document.querySelector('#')` melempar `SyntaxError` karena
`'#'` bukan CSS selector yang valid.

```
dashboard:736 Uncaught SyntaxError: Failed to execute 'querySelector' on 'Document':
'#' is not a valid selector.
```

**Solusi:** Tambah guard untuk skip ketika `href` adalah tepat `'#'`.

```javascript
// SEBELUM
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href')); // crash!
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

// SESUDAH
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (!href || href === '#') return; // ← guard
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});
```

---

### 2. Fix Tombol "My Profile" Tidak Mengarah ke Halaman Profile

**File:** `resources/views/dashboard.blade.php` (baris ~482)

**Masalah:** Link "My Profile" di dropdown user menu menggunakan `href="#"` sehingga tidak
mengarahkan ke halaman profil.

```html
<!-- SEBELUM -->
<a href="#" class="...">
    <span>My Profile</span>
</a>

<!-- SESUDAH -->
<a href="{{ route('profile.edit') }}" class="...">
    <span>My Profile</span>
</a>
```

**Route yang dituju:** `GET /profile` → `ProfileController@edit`

---

### 3. Redesign Halaman My Profile

**File:** `resources/views/profile/edit.blade.php`

**Perubahan layout:**
- Grid field dari 2 kolom → 3 kolom (lebih lebar)
- Setiap field dibungkus card `bg-gray-50 rounded-xl p-4` dengan hover effect
- Label field menggunakan `text-[11px] uppercase tracking-wide` (lebih terbaca)
- Value field menggunakan `text-base font-semibold` (lebih besar)
- Header card section: ikon + judul + subtitle deskripsi
- Sidebar kiri: menu tab + info akun lengkap (username, email, login terakhir, bergabung)
- Setiap seksi (Data Pribadi, Pekerjaan, Kontak) punya warna ikon berbeda

**Struktur baru:**
```
┌─────────────────────────────────────────────────────┐
│  Avatar   Nama Lengkap                   Active      │
│           Jabatan                                    │
│           @ username                                 │
└─────────────────────────────────────────────────────┘

┌──── Sidebar ──────┐  ┌───── Content ─────────────────┐
│  MENU             │  │  ┌─ Data Pribadi ────────────┐ │
│  > Informasi      │  │  │  [ECI] [Nama Depan] [Blkg]│ │
│    Pribadi (aktif)│  │  │  [Panggilan] [Gender] [Agama]│
│  > Keamanan       │  │  │  [Status] [Tgl Lahir] [Tmpt]│
│                   │  │  └──────────────────────────┘ │
│  INFO AKUN        │  │  ┌─ Info Pekerjaan ──────────┐ │
│  @ username       │  │  │  [Jabatan] [Divisi] ...   │ │
│  ✉ email          │  │  └──────────────────────────┘ │
│  🕐 last login    │  │  ┌─ Kontak & Alamat ─────────┐ │
│  📅 joined        │  │  │  [Email] [Telepon] ...    │ │
└───────────────────┘  │  └──────────────────────────┘ │
                       └────────────────────────────────┘
```

**Tab Keamanan:**
- Form ubah password (3 field: lama, baru, konfirmasi)
- Strength indicator real-time (✓/✗ min 8 karakter, cocok)
- Tombol show/hide password
- Konfirmasi modal sebelum submit
- AJAX submit ke `POST /profile/change-password`

**Fix konflik Tailwind:** Modal menggunakan `style="display:none"` dan JS
`style.display = 'flex'/'none'` (bukan toggle class `hidden` + `flex` yang berkonflik).

---

### 4. Hapus Banner Merah di Header Profile

**File:** `resources/views/profile/edit.blade.php`

**Masalah:** Header card memiliki banner merah gradient (`h-40 bg-gradient-to-r from-red-900`)
yang memakan banyak ruang vertikal dan tidak informatif.

**Solusi:** Hapus elemen banner, ganti dengan header putih bersih. Avatar berubah dari
overlay di atas banner → inline dengan nama dan informasi.

```
SEBELUM:
┌─────────────────────────────────────────────────────┐
│  ██████████████████ MERAH ██████████████████████████ │  ← dihapus
│  ██████████████████████████████████████████████████ │
│  ██████████████████████████████████████████████████ │
├──────────────────────────────────────────────────────│
│  [Avatar]  Nama   │                       ● Active   │
└─────────────────────────────────────────────────────┘

SESUDAH:
┌─────────────────────────────────────────────────────┐
│  [Avatar]  Nama Lengkap                   ● Active   │
│            Jabatan  • @ username                     │
└─────────────────────────────────────────────────────┘
```

---

### 5. Sistem Staging Ticket (Validasi Tiket Customer)

**Latar belakang:**
Tiket yang diajukan customer (melalui form web Jarvies) tidak boleh langsung masuk ke
tabel `ticket` (production). Harus melalui validasi admin/employee terlebih dahulu.

**Flow baru:**
```
Customer (Jarvies)                 Admin/Employee (EcoSystem)
      │                                     │
      │  POST /api/staging-tickets           │
      │─────────────────────────────────────►│
      │                                      │  staging_tickets
      │                                      │  status = unvalidated
      │  ◄───────────────────────────────────│
      │  response: "Menunggu validasi"       │
      │                                      │  Admin buka /staging-tickets
      │                                      │  Lihat daftar → klik Validasi
      │                                      │
      │               [APPROVE]              │
      │                                      │  DB::transaction {
      │                                      │    Ticket::create()
      │                                      │    staging.status = approved
      │                                      │    staging.ticket_id = $ticket->id
      │                                      │  }
      │                                      │
      │               [REJECT]               │
      │                                      │  staging.status = rejected
      │                                      │  staging.rejection_reason = alasan
```

---

#### 5a. Migration — `staging_tickets` Table

**File:** `database/migrations/2026_02_23_000000_create_staging_tickets_table.php`

```sql
CREATE TABLE staging_tickets (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id         BIGINT UNSIGNED NULL,  -- FK → customer.customer_id
    description         TEXT NOT NULL,
    ticket_priority     ENUM('Low','Medium','High') DEFAULT 'Medium',
    status              ENUM('unvalidated','approved','rejected') DEFAULT 'unvalidated',
    rejection_reason    TEXT NULL,
    channel             VARCHAR(20) DEFAULT 'web',   -- 'web' | 'email'
    email_thread_id     VARCHAR(255) NULL,            -- untuk cegah duplikat email
    submitted_by_email  VARCHAR(255) NULL,
    validated_by        BIGINT UNSIGNED NULL,         -- FK → employee.employee_id
    validated_at        TIMESTAMP NULL,
    ticket_id           BIGINT UNSIGNED NULL UNIQUE,  -- FK → ticket.ticket_id (setelah approve)
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    INDEX (status, customer_id),
    INDEX (created_at),
    INDEX (email_thread_id)
);
```

**Aturan penting:**
- `ticket_id UNIQUE` → mencegah 1 staging di-approve lebih dari satu kali (double validation)
- `email_thread_id` INDEX → cek duplikat saat email masuk

---

#### 5b. Model — `StagingTicket`

**File:** `app/Models/StagingTicket.php`

| Method / Scope | Kegunaan |
|---|---|
| `scopeUnvalidated()` | Filter `status = unvalidated` |
| `scopeApproved()` | Filter `status = approved` |
| `scopeRejected()` | Filter `status = rejected` |
| `isUnvalidated()` | Boolean check |
| `isProcessed()` | `true` jika approved atau rejected |
| `customer()` | Relasi ke `Customer` |
| `validator()` | Relasi ke `Employee` (yang approve/reject) |
| `ticket()` | Relasi ke `Ticket` (setelah approve) |

---

#### 5c. Service — `StagingTicketService`

**File:** `app/Services/StagingTicketService.php`

| Method | Dipanggil dari | Keterangan |
|---|---|---|
| `createFromWeb(array, customerId)` | `TicketController@store` (customer) | Simpan submission dari form web |
| `createFromEmail(array)` | `EmailController@processInbox` | Simpan email masuk ke staging (bukan langsung jadi ticket) |
| `approve(StagingTicket, employeeId)` | `StagingTicketController@approve` | DB transaction: buat Ticket + update staging |
| `reject(StagingTicket, employeeId, reason)` | `StagingTicketController@reject` | Set status rejected + catat alasan |

**Guard double validation** di `approve()` dan `reject()`:
```php
if ($staging->isProcessed()) {
    throw new \LogicException("Staging ticket sudah berstatus '{$staging->status}'.");
}
```

**DB Transaction di `approve()`:**
```php
return DB::transaction(function () use ($staging, $validatedBy) {
    $ticket = Ticket::create([...]);   // 1. Buat ticket resmi
    $staging->update([                 // 2. Update staging
        'status'    => 'approved',
        'ticket_id' => $ticket->ticket_id,
        ...
    ]);
    return $ticket;
});
```
Jika salah satu gagal → kedua operasi di-rollback.

---

#### 5d. Controller — `StagingTicketController`

**File:** `app/Http/Controllers/StagingTicketController.php`

| Method | Route | Akses |
|---|---|---|
| `view()` | `GET /staging-tickets` | Admin/Employee (web view) |
| `index()` | `GET /api/staging-tickets` | Admin/Employee (semua), Customer (milik sendiri) |
| `show($id)` | `GET /api/staging-tickets/{id}` | Admin/Employee/Customer (customer hanya miliknya) |
| `store()` | `POST /api/staging-tickets` | Customer saja (role 3) |
| `approve($id)` | `POST /api/staging-tickets/{id}/approve` | Admin/Employee/Helpdesk |
| `reject($id)` | `POST /api/staging-tickets/{id}/reject` | Admin/Employee/Helpdesk |
| `statistics()` | `GET /api/staging-tickets/statistics` | Admin/Employee/Helpdesk |

---

#### 5e. Perubahan `TicketController@store`

**File:** `app/Http/Controllers/TicketController.php`

```
SEBELUM:
  Role 3 (Customer) → Ticket::create() → tabel ticket langsung

SESUDAH:
  Role 3 (Customer) → StagingTicketService::createFromWeb() → tabel staging_tickets
  Role 1 (Admin)    → Ticket::create() → tabel ticket (bypass staging, tetap sama)
```

Response untuk customer sekarang:
```json
{
    "success": true,
    "staging": true,
    "message": "Tiket Anda telah dikirim dan sedang menunggu validasi admin.",
    "data": {
        "id": 1,
        "status": "unvalidated",
        "ticket_priority": "Medium",
        "created_at": "2026-02-23T..."
    }
}
```

---

#### 5f. View Admin — Halaman Validasi Staging

**File:** `resources/views/staging/index.blade.php`
**URL:** `/staging-tickets`

**Fitur:**
- **Stats bar**: jumlah Menunggu / Disetujui / Ditolak (real-time via API)
- **Filter status**: dropdown (semua / menunggu / disetujui / ditolak)
- **Tabel**: ID, Customer, Deskripsi (truncate 60 char), Prioritas, Channel, Status, Tgl Submit, Aksi
- **Modal detail**: tampil info lengkap + tombol Setujui / Tolak
- **Approve**: konfirmasi langsung → `POST /api/staging-tickets/{id}/approve`
- **Reject**: muncul textarea alasan → `POST /api/staging-tickets/{id}/reject`
- **Pagination**: per 15 data

---

#### 5g. Routes Baru

**`routes/api.php`:**
```php
Route::prefix('staging-tickets')->group(function () {
    Route::get('/statistics', [StagingTicketController::class, 'statistics']);
    Route::get('/', [StagingTicketController::class, 'index']);
    Route::post('/', [StagingTicketController::class, 'store']);
    Route::get('/{id}', [StagingTicketController::class, 'show']);
    Route::post('/{id}/approve', [StagingTicketController::class, 'approve']);
    Route::post('/{id}/reject', [StagingTicketController::class, 'reject']);
});
```

**`routes/web.php`:**
```php
Route::get('/staging-tickets', [StagingTicketController::class, 'view'])
    ->name('staging.index');
```

---

## Panduan Integrasi untuk Project Jarvies (Customer Side)

### Submit Tiket Baru

```http
POST /api/staging-tickets
Content-Type: application/json
Cookie: [session cookie dari login]

{
    "description": "Deskripsi masalah yang dialami customer...",
    "ticket_priority": "Medium"
}
```

**Response sukses (201):**
```json
{
    "success": true,
    "staging": true,
    "message": "Tiket Anda telah dikirim dan sedang menunggu validasi admin.",
    "data": {
        "id": 42,
        "status": "unvalidated",
        "ticket_priority": "Medium",
        "created_at": "2026-02-23T10:00:00.000000Z"
    }
}
```

### Cek Status Tiket yang Diajukan

```http
GET /api/staging-tickets?status=unvalidated
```

atau per-tiket:
```http
GET /api/staging-tickets/42
```

**Response field penting:**
```json
{
    "data": {
        "id": 42,
        "status": "approved",          // unvalidated | approved | rejected
        "rejection_reason": null,       // terisi jika rejected
        "ticket_id": 155,              // terisi jika approved
        "ticket_number": "2602-CUST-0001"  // nomor tiket resmi
    }
}
```

### Logic di Jarvies Berdasarkan Status

| Status | Tampilkan ke Customer |
|---|---|
| `unvalidated` | "Tiket Anda sedang direview oleh tim kami" |
| `approved` | "Tiket diterima! No. Tiket: XXXX" + link ke detail tiket |
| `rejected` | "Tiket ditolak. Alasan: [rejection_reason]" |

---

## File Index (Semua File yang Dibuat/Diubah)

### File Baru
```
database/migrations/2026_02_23_000000_create_staging_tickets_table.php
app/Models/StagingTicket.php
app/Services/StagingTicketService.php
app/Http/Controllers/StagingTicketController.php
resources/views/staging/index.blade.php
resources/views/profile/edit.blade.php
docs/CHANGES.md  ← file ini
```

### File Diubah
```
resources/views/dashboard.blade.php
   - Fix querySelector('#') SyntaxError (smooth scroll)
   - Fix link "My Profile" → route('profile.edit')

app/Http/Controllers/TicketController.php
   - import StagingTicketService
   - store(): customer (role 3) → staging, bukan langsung ke ticket

routes/api.php
   - import StagingTicketController
   - tambah group prefix 'staging-tickets'

routes/web.php
   - import StagingTicketController
   - tambah route GET /staging-tickets
```

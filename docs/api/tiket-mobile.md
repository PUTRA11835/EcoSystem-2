# API Dokumentasi — Tiket & Delivery Support (Mobile Employee)

> Versi: 1.0
> Dibuat: 2026-03-30
> Digunakan oleh: Aplikasi Mobile Employee

---

## Deskripsi

REST API ini menyediakan semua data yang dibutuhkan layar **TicketListScreen**, **TicketDetailScreen**, **TicketCreateScreen**, dan **SupportListScreen** pada aplikasi mobile employee.

Semua endpoint berada di bawah prefix `/api/mobile/employee/` dan **wajib menggunakan Bearer Token** yang diperoleh dari endpoint login.

---

## Base URL

```
https://<domain>/api/mobile/employee
```

---

## Autentikasi

Semua endpoint memerlukan header:

```
Authorization: Bearer <access_token>
```

Token diperoleh dari:
- `POST /api/mobile/employee/auth/login`
- `POST /api/mobile/employee/auth/refresh`

Access token berlaku **15 menit**. Gunakan refresh token (berlaku 7 hari) untuk memperbarui.

---

## Ringkasan Endpoint

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/tickets` | List tiket + stats |
| POST | `/tickets` | Buat tiket baru |
| GET | `/tickets/{id}` | Detail tiket |
| GET | `/tickets/{id}/messages` | Daftar pesan tiket |
| POST | `/tickets/{id}/messages` | Kirim pesan |
| POST | `/tickets/{id}/ownership` | Ambil alih tiket |
| PUT | `/tickets/{id}/mandays` | Update mandays |
| POST | `/tickets/{id}/send-to-customer` | Kirim notifikasi ke customer |
| GET | `/support-tickets` | List Delivery Support |
| GET | `/support-tickets/{id}` | Detail Delivery Support |

---

## Penjelasan Field Global

| Field | Tipe | Keterangan |
|-------|------|------------|
| `id` | integer | ID unik tiket |
| `title` | string | Judul tiket (maps ke kolom `subject` di DB) |
| `status` | string | `Open` \| `In Progress` \| `Hold` \| `Reply` \| `Closed` |
| `priority` | string | `Low` \| `Medium` \| `High` |
| `type` | string | `Bug` \| `Feature Request` \| `Improvement` \| `Question` |
| `jarvies_status` | string | Status internal sistem Jarvies |
| `customer.id` | integer | ID customer |
| `customer.name` | string | Nama customer (dari `customer_basic_data.name_1`) |
| `assigned_user.id` | integer | ID employee yang handle tiket |
| `assigned_user.name` | string | Nama employee |
| `members` | array | Anggota tim tiket |
| `updated_at` | string | Format: `YYYY-MM-DD` |
| `created_at` | string | Format: `YYYY-MM-DD` |

---

## Endpoint Detail

---

### 1. List Tiket

**`GET /tickets`**

Menampilkan semua tiket dengan stats ringkasan di bagian atas. Mendukung search, filter status, dan filter "Mine".

#### Query Parameters

| Parameter | Tipe | Required | Deskripsi |
|-----------|------|----------|-----------|
| `search` | string | No | Cari berdasarkan judul atau nama customer |
| `status` | string | No | `Open` \| `In Progress` \| `Hold` \| `Reply` \| `Closed` |
| `assigned_to_me` | boolean | No | `true` → hanya tiket yang di-assign ke user login |
| `page` | integer | No | Halaman (default: 1, per halaman: 15) |

#### Response `200 OK`

```json
{
  "success": true,
  "stats": {
    "total": 42,
    "in_progress": 10,
    "hold": 5,
    "closed": 20
  },
  "data": [
    {
      "id": 1042,
      "title": "Login page error on iOS",
      "status": "In Progress",
      "priority": "High",
      "customer": {
        "id": 1,
        "name": "PT. Abadi Jaya"
      },
      "assigned_user": {
        "id": 3,
        "name": "John Doe"
      },
      "updated_at": "2026-03-11"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "total": 42
  }
}
```

---

### 2. Buat Tiket Baru

**`POST /tickets`**

Content-Type: `multipart/form-data` (karena ada optional file upload)

#### Request Body

| Field | Tipe | Required | Deskripsi |
|-------|------|----------|-----------|
| `title` | string | Yes | Judul tiket (max 255 karakter) |
| `description` | string | Yes | Deskripsi detail masalah |
| `type` | string | Yes | `Bug` \| `Feature Request` \| `Improvement` \| `Question` |
| `priority` | string | Yes | `Low` \| `Medium` \| `High` |
| `attachment` | file | No | PDF/PNG/JPG/JPEG, max 10MB |

#### Response `201 Created`

```json
{
  "success": true,
  "message": "Tiket berhasil dibuat.",
  "data": {
    "id": 1043,
    "title": "Payroll calculation wrong",
    "description": "Payroll calculation is off by 10% for...",
    "status": "Open",
    "priority": "Medium",
    "type": "Bug",
    "jarvies_status": null,
    "customer": {
      "id": null,
      "name": null
    },
    "assigned_user": {
      "id": 3,
      "name": "John Doe"
    },
    "members": [],
    "created_at": "2026-03-30",
    "updated_at": "2026-03-30"
  }
}
```

#### Error `422 Unprocessable Entity`

```json
{
  "success": false,
  "message": "Validasi gagal.",
  "errors": {
    "title": ["The title field is required."],
    "type": ["The selected type is invalid."]
  }
}
```

---

### 3. Detail Tiket

**`GET /tickets/{id}`**

Menampilkan detail lengkap satu tiket, termasuk anggota tim.

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID tiket |

#### Response `200 OK`

```json
{
  "success": true,
  "data": {
    "id": 1042,
    "title": "Login page error on iOS",
    "description": "Users cannot log in via the iOS app since version 3.2.1...",
    "status": "In Progress",
    "priority": "High",
    "type": "Bug",
    "jarvies_status": "Open",
    "customer": {
      "id": 1,
      "name": "PT. Abadi Jaya"
    },
    "assigned_user": {
      "id": 3,
      "name": "John Doe"
    },
    "members": [
      { "id": 3, "name": "John Doe" },
      { "id": 5, "name": "Jane Smith" }
    ],
    "created_at": "2026-03-01",
    "updated_at": "2026-03-11"
  }
}
```

#### Error `404 Not Found`

```json
{
  "message": "No query results for model [App\\Models\\Ticket]."
}
```

---

### 4. List Pesan Tiket

**`GET /tickets/{id}/messages`**

Menampilkan riwayat chat tiket (tidak termasuk internal notes).
Urutan: terlama → terbaru (untuk tampilan bubble chat).

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID tiket |

#### Response `200 OK`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sender": {
        "id": 1,
        "name": "PT. Abadi Jaya"
      },
      "content": "We are still experiencing this issue on mobile...",
      "is_from_team": false,
      "created_at": "2026-03-11T09:30:00Z"
    },
    {
      "id": 2,
      "sender": {
        "id": 3,
        "name": "John Doe"
      },
      "content": "Thank you for the report. We are investigating now.",
      "is_from_team": true,
      "created_at": "2026-03-11T10:15:00Z"
    }
  ]
}
```

**Keterangan `is_from_team`:**
- `true` → pesan dari **employee/tim** → tampilkan di **kanan** (bubble kanan)
- `false` → pesan dari **customer** → tampilkan di **kiri** (bubble kiri)

---

### 5. Kirim Pesan

**`POST /tickets/{id}/messages`**

Mengirim pesan baru ke tiket. `is_from_team` otomatis `true` karena hanya employee yang bisa mengakses endpoint ini.

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID tiket |

#### Request Body (JSON)

```json
{
  "content": "We have identified the issue and will release a fix by EOD."
}
```

| Field | Tipe | Required | Deskripsi |
|-------|------|----------|-----------|
| `content` | string | Yes | Isi pesan |

#### Response `201 Created`

```json
{
  "success": true,
  "message": "Pesan berhasil dikirim.",
  "data": {
    "id": 3,
    "sender": {
      "id": 3,
      "name": "John Doe"
    },
    "content": "We have identified the issue and will release a fix by EOD.",
    "is_from_team": true,
    "created_at": "2026-03-30T08:45:00Z"
  }
}
```

---

### 6. Ambil Alih Tiket (Take Ownership)

**`POST /tickets/{id}/ownership`**

Mengassign tiket ke employee yang sedang login (menggantikan PIC sebelumnya).

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID tiket |

#### Request Body

Tidak diperlukan (no body).

#### Response `200 OK`

```json
{
  "success": true,
  "message": "Tiket berhasil diambil alih.",
  "data": {
    "id": 1042,
    "title": "Login page error on iOS",
    "assigned_user": {
      "id": 7,
      "name": "Alice Tan"
    },
    ...
  }
}
```

---

### 7. Update Mandays

**`PUT /tickets/{id}/mandays`**

Memperbarui estimasi mandays tiket.

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID tiket |

#### Request Body (JSON)

```json
{
  "man_days": 2.5
}
```

| Field | Tipe | Required | Deskripsi |
|-------|------|----------|-----------|
| `man_days` | number | Yes | Jumlah mandays (min: 0) |

#### Response `200 OK`

```json
{
  "success": true,
  "message": "Mandays berhasil diperbarui.",
  "data": {
    "man_days": 2.5
  }
}
```

---

### 8. Kirim Notifikasi ke Customer

**`POST /tickets/{id}/send-to-customer`**

Mengubah status tiket menjadi `Reply` dan mencatat waktu balasan terakhir dari agent.

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID tiket |

#### Request Body

Tidak diperlukan (no body).

#### Response `200 OK`

```json
{
  "success": true,
  "message": "Notifikasi berhasil dikirim ke customer."
}
```

---

### 9. List Delivery Support

**`GET /support-tickets`**

Menampilkan daftar Delivery Support untuk SupportListScreen.

#### Query Parameters

| Parameter | Tipe | Required | Deskripsi |
|-----------|------|----------|-----------|
| `search` | string | No | Cari berdasarkan nama support atau nama customer |
| `status` | string | No | `In Process` \| `Not Started` \| `Closed` \| `All` |
| `page` | integer | No | Halaman (default: 1) |

**Mapping status → kondisi DB:**
| Mobile label | Kondisi |
|-------------|---------|
| `In Process` | `0 < calculated_progress < 100` |
| `Not Started` | `calculated_progress = 0` |
| `Closed` | `calculated_progress >= 100` |
| `All` / kosong | Tidak difilter |

**`progress_percent`** = `calculated_progress / 100` (range 0.0 – 1.0)

#### Response `200 OK`

```json
{
  "success": true,
  "data": [
    {
      "id": 2031,
      "title": "Payroll module calculation error",
      "status": "In Process",
      "customer": {
        "id": 1,
        "name": "PT. Abadi Jaya"
      },
      "pic_user": {
        "id": 3,
        "name": "John Doe"
      },
      "start_date": "2026-03-01",
      "end_date": "2026-03-15",
      "progress_percent": 0.60,
      "team_members": [
        { "id": 3, "name": "John Doe" },
        { "id": 5, "name": "Jane Smith" }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "total": 20
  }
}
```

---

### 10. Detail Delivery Support

**`GET /support-tickets/{id}`**

#### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID delivery support |

#### Response `200 OK`

```json
{
  "success": true,
  "data": {
    "id": 2031,
    "title": "Payroll module calculation error",
    "status": "In Process",
    "customer": {
      "id": 1,
      "name": "PT. Abadi Jaya"
    },
    "pic_user": {
      "id": 3,
      "name": "John Doe"
    },
    "start_date": "2026-03-01",
    "end_date": "2026-03-15",
    "progress_percent": 0.60,
    "team_members": [
      { "id": 3, "name": "John Doe" },
      { "id": 5, "name": "Jane Smith" }
    ]
  }
}
```

---

## Error Responses

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Token tidak ditemukan. Silakan login terlebih dahulu.",
  "code": "UNAUTHENTICATED"
}
```

```json
{
  "success": false,
  "message": "Access token sudah expired. Gunakan refresh token untuk memperbarui.",
  "code": "ACCESS_TOKEN_EXPIRED"
}
```

### 403 Forbidden

```json
{
  "success": false,
  "message": "Akses ditolak. Endpoint ini hanya untuk employee.",
  "code": "NOT_EMPLOYEE"
}
```

### 404 Not Found

```json
{
  "message": "No query results for model [App\\Models\\Ticket]."
}
```

### 422 Unprocessable Entity

```json
{
  "success": false,
  "message": "Validasi gagal.",
  "errors": {
    "title": ["The title field is required."],
    "priority": ["The selected priority is invalid."]
  }
}
```

### 500 Internal Server Error

```json
{
  "message": "Server Error"
}
```

---

## Status Value Reference

### Ticket Status

| DB Value | Label di API | Keterangan |
|----------|-------------|------------|
| `open` | `Open` | Tiket baru dibuat |
| `in_progress` | `In Progress` | Sedang dikerjakan |
| `hold` | `Hold` | Ditahan / menunggu |
| `reply` | `Reply` | Menunggu balasan customer |
| `closed` | `Closed` | Selesai |

### Support Ticket Status (dari calculated_progress)

| Kondisi | Label di API |
|---------|-------------|
| `calculated_progress = 0` | `Not Started` |
| `0 < calculated_progress < 100` | `In Process` |
| `calculated_progress >= 100` | `Closed` |

---

## File & Arsitektur

| File | Deskripsi |
|------|-----------|
| `app/Http/Controllers/Mobile/TicketController.php` | Controller tiket mobile |
| `app/Http/Controllers/Mobile/SupportTicketController.php` | Controller delivery support mobile |
| `app/Http/Resources/Mobile/TicketListResource.php` | Transformer item list tiket |
| `app/Http/Resources/Mobile/TicketDetailResource.php` | Transformer detail tiket |
| `app/Http/Resources/Mobile/TicketMessageResource.php` | Transformer pesan tiket |
| `app/Http/Resources/Mobile/SupportTicketResource.php` | Transformer delivery support |
| `database/migrations/2026_03_30_000000_add_category_to_ticket_table.php` | Migrasi kolom `category` di tabel `ticket` |
| `routes/api.php` | Definisi route (di bawah `mobile/employee` prefix + `mobile.employee` middleware) |

---

## Catatan Teknis

1. **Kolom `title` di API** → tersimpan sebagai kolom `subject` di tabel `ticket`.
2. **Kolom `type` di API** → tersimpan sebagai kolom `category` di tabel `ticket`
   _(berbeda dengan kolom `type` di tabel `delivery_support` yang berisi AMS/MO/ATS/Project/Internal)_.
3. **`is_from_team`** pada pesan **tidak dikirim dari client** — server menentukan otomatis berdasarkan `sender_type` (`employee` = `true`, `customer` = `false`).
4. **Filter "Mine"** (`assigned_to_me=true`) → server filter `employee_id = auth()->employee_id`.
5. **Soft Delete** → tiket dengan `deleted_at` tidak null tidak akan muncul di list atau detail.
6. **File upload** menggunakan Laravel `Storage::disk('public')`. Pastikan `php artisan storage:link` sudah dijalankan.
7. Jalankan migrasi setelah deploy: `php artisan migrate`.

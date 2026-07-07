# EcoSystem Lite API — Dokumentasi Integrasi

> Versi: 1.0  
> Base URL: `https://<your-domain>/api/lite`  
> Format data: `application/json`

---

## Daftar Isi

1. [Mekanisme Autentikasi](#1-mekanisme-autentikasi)
2. [Alur Penggunaan API](#2-alur-penggunaan-api)
3. [Endpoint Auth](#3-endpoint-auth)
   - [POST /auth/login](#31-post-authlogin)
   - [POST /auth/logout](#32-post-authlogout)
   - [GET /auth/me](#33-get-authme)
4. [Endpoint Dashboard](#4-endpoint-dashboard)
   - [GET /dashboard](#41-get-dashboard)
5. [Endpoint Tiket](#5-endpoint-tiket)
   - [GET /tickets](#51-get-tickets)
   - [GET /tickets/statistics](#52-get-ticketsstatistics)
   - [GET /tickets/{id}](#53-get-ticketsid)
   - [PATCH /tickets/{id}/status](#54-patch-ticketsidstatus)
   - [GET /tickets/{ticketId}/messages](#55-get-ticketsticketidmessages)
   - [POST /tickets/{ticketId}/messages](#56-post-ticketsticketidmessages)
   - [GET /tickets/my](#57-get-ticketsmy)
6. [Endpoint Profil](#6-endpoint-profil)
   - [GET /profile](#61-get-profile)
   - [PATCH /profile/change-password](#62-patch-profilechange-password)
7. [Endpoint Notifikasi](#7-endpoint-notifikasi)
   - [GET /notifications](#71-get-notifications)
   - [GET /notifications/unread-count](#72-get-notificationsunread-count)
   - [PUT /notifications/{id}/read](#73-put-notificationsidread)
   - [PUT /notifications/read-all](#74-put-notificationsread-all)
   - [DELETE /notifications/{id}](#75-delete-notificationsid)
   - [DELETE /notifications/bulk-delete](#76-delete-notificationsbulk-delete)
8. [Kode Status HTTP](#8-kode-status-http)
9. [Daftar Nilai Enum](#9-daftar-nilai-enum)
10. [Catatan Penting](#10-catatan-penting)

---

## 1. Mekanisme Autentikasi

Lite API mendukung dua mekanisme autentikasi:

### a) Bearer Token (Disarankan untuk SPA)

Setelah login berhasil, gunakan nilai `data.token` dari respons login sebagai Bearer token pada setiap request yang membutuhkan autentikasi.

```
Authorization: Bearer <token>
```

**Format token:** `base64(ECI|timestamp|employee)`  
**Masa berlaku:** 24 jam dari waktu login.

### b) Session Cookie (Untuk Web App tradisional)

Browser akan otomatis menyimpan dan mengirim session cookie (`ecosystem-session`) setelah login. Tidak perlu konfigurasi tambahan.

### Endpoint Publik (Tidak perlu autentikasi)

| Endpoint | Keterangan |
|----------|-----------|
| `POST /api/lite/auth/login` | Login — satu-satunya endpoint tanpa autentikasi |

### Semua Endpoint Lain

Wajib menyertakan salah satu dari:
- Header `Authorization: Bearer <token>`, **atau**
- Session cookie yang valid dari browser

---

## 2. Alur Penggunaan API

```
1. POST /api/lite/auth/login
   → Terima token + userData

2. Simpan token di frontend (localStorage / memory)

3. Setiap request berikutnya:
   → Tambahkan header: Authorization: Bearer <token>

4. Jika response 401 Unauthorized:
   → Token expired atau tidak valid
   → Arahkan user ke halaman login

5. Saat user logout:
   → POST /api/lite/auth/logout
   → Hapus token dari frontend
```

---

## 3. Endpoint Auth

### 3.1 POST /auth/login

Login ke sistem. Mengembalikan token dan data user.

**Method:** `POST`  
**URL:** `/api/lite/auth/login`  
**Auth:** Tidak diperlukan  
**Rate Limit:** 5 request/menit per IP

#### Request Headers

| Header | Value | Wajib |
|--------|-------|-------|
| Content-Type | `application/json` | Ya |

#### Request Body

| Field | Type | Wajib | Deskripsi |
|-------|------|-------|-----------|
| `email` | `string` | Ya | Email, username (ECI), atau nomor telepon |
| `password` | `string` | Ya | Password (min 6 karakter) |
| `remember` | `boolean` | Tidak | Aktifkan remember-me cookie |

#### Contoh Request

```json
{
  "email": "john.doe@company.com",
  "password": "secretpassword123",
  "remember": false
}
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "RUNJTDE3fDE3NTExMjM0NTZ8ZW1wbG95ZWU=",
    "user": {
      "id": 42,
      "type": "employee",
      "employee_type": "Internal",
      "eci": "ECI017",
      "name": "John Doe",
      "nick_name": "John",
      "email": "john.doe@company.com",
      "phone": "081234567890",
      "position": "Consultant",
      "department": "IT Services",
      "role": {
        "id": 2,
        "name": "Delivery Support User"
      },
      "role_ids": [2],
      "roles": [
        { "id": 2, "name": "Delivery Support User" }
      ]
    }
  }
}
```

#### Contoh Response: Akun Baru — Harus Ganti Password (200)

```json
{
  "success": true,
  "require_password_change": true,
  "message": "Please check your email to set up your new password.",
  "email": "jo**@company.com"
}
```

> **Catatan:** Jika `require_password_change: true`, arahkan user ke halaman password setup. Token tidak dikembalikan pada kondisi ini.

#### Contoh Response Error (401)

```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

#### Contoh Response Error (403)

```json
{
  "success": false,
  "message": "Your account does not have access to this system. Please contact your administrator."
}
```

#### Contoh Response Error Validasi (422)

```json
{
  "success": false,
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

---

### 3.2 POST /auth/logout

Logout dari sistem. Menghapus session dan mencatat aktivitas logout.

**Method:** `POST`  
**URL:** `/api/lite/auth/logout`  
**Auth:** Diperlukan

#### Request Headers

| Header | Value | Wajib |
|--------|-------|-------|
| Authorization | `Bearer <token>` | Ya (jika tidak pakai cookie) |
| Content-Type | `application/json` | Ya |

#### Contoh Request

```
POST /api/lite/auth/logout
Authorization: Bearer RUNJTDE3fDE3NTExMjM0NTZ8ZW1wbG95ZWU=
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "message": "Logout successful"
}
```

#### Contoh Response Error (500)

```json
{
  "success": false,
  "message": "An error occurred during logout"
}
```

---

### 3.3 GET /auth/me

Mengambil data user yang sedang login berdasarkan token atau session.

**Method:** `GET`  
**URL:** `/api/lite/auth/me`  
**Auth:** Diperlukan

#### Request Headers

| Header | Value | Wajib |
|--------|-------|-------|
| Authorization | `Bearer <token>` | Ya (jika tidak pakai cookie) |

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": {
    "id": 42,
    "type": "employee",
    "employee_type": "Internal",
    "eci": "ECI017",
    "name": "John Doe",
    "nick_name": "John",
    "email": "john.doe@company.com",
    "phone": "081234567890",
    "position": "Consultant",
    "department": "IT Services",
    "role": {
      "id": 2,
      "name": "Delivery Support User"
    },
    "role_ids": [2],
    "roles": [
      { "id": 2, "name": "Delivery Support User" }
    ]
  }
}
```

#### Contoh Response Error (401)

```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

---

## 4. Endpoint Dashboard

### 4.1 GET /dashboard

Mengambil data dashboard yang disesuaikan berdasarkan role user yang sedang login.

**Method:** `GET`  
**URL:** `/api/lite/dashboard`  
**Auth:** Diperlukan

#### Request Headers

| Header | Value | Wajib |
|--------|-------|-------|
| Authorization | `Bearer <token>` | Ya (jika tidak pakai cookie) |

#### Contoh Response Sukses (200) — EC Administrator

```json
{
  "success": true,
  "data": {
    "employee": 120,
    "customers": 85,
    "active_projects": 12,
    "total_tickets": 450,
    "ticket_stats": {
      "total": 450,
      "open": 40,
      "inprocess": 60,
      "waiting_on_customer": 15,
      "waiting_on_3rd_party": 8,
      "waiting_to_confirmation": 5,
      "hold": 10,
      "cancelled": 20,
      "closed": 292
    },
    "ticket_chart": {
      "labels": ["08 Jun", "09 Jun", "..."],
      "data": [3, 5, 2, 8]
    },
    "recent_tickets": [
      {
        "ticket_id": 101,
        "ticket_number": "TKT-2024-001",
        "description": "Tidak bisa login ke sistem",
        "status": "open",
        "ticket_priority": "High",
        "created_at": "2024-06-15T10:30:00.000000Z",
        "customer_name": "PT. ABC Indonesia",
        "pic_name": "Jane Smith"
      }
    ],
    "team_load": [
      {
        "employee_id": 12,
        "name": "Jane Smith",
        "open_count": 8
      }
    ],
    "staging_pending": 3,
    "sla_summary": {
      "total": 430,
      "met": 380,
      "breached": 20,
      "compliance_rate": 95.0
    }
  }
}
```

#### Data Dashboard Per Role

| Field | Admin | EC User | DS Head | Helpdesk | DS Manager | DS User |
|-------|:-----:|:-------:|:-------:|:--------:|:----------:|:-------:|
| `ticket_stats` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `ticket_chart` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `recent_tickets` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `team_load` | ✓ | ✓ | ✓ | - | ✓ | - |
| `sla_summary` | ✓ | ✓ | ✓ | ✓ | ✓ | - |
| `staging_pending` | ✓ | ✓ | - | ✓ | - | - |
| `unassigned_count` | - | - | - | ✓ | ✓ | - |
| `very_high_count` | - | - | - | ✓ | ✓ | ✓ |
| `priority_breakdown` | - | - | - | ✓ | ✓ | ✓ |
| `urgent_tickets` | - | - | - | ✓ | ✓ | ✓ |
| `as_pic_count` | - | - | - | - | - | ✓ |
| `active_count` | - | - | - | - | - | ✓ |
| `timesheet_pending` | - | - | ✓ | - | - | - |

---

## 5. Endpoint Tiket

### 5.1 GET /tickets

Mengambil daftar tiket dengan role-based filtering dan pagination.

**Method:** `GET`  
**URL:** `/api/lite/tickets`  
**Auth:** Diperlukan

#### Query Parameters

| Parameter | Type | Wajib | Deskripsi |
|-----------|------|-------|-----------|
| `page` | `integer` | Tidak | Nomor halaman (default: 1) |
| `per_page` | `integer` | Tidak | Jumlah per halaman (default: 20, max: 100) |
| `status` | `string` | Tidak | Filter berdasarkan status tiket |
| `priority` | `string` | Tidak | Filter berdasarkan prioritas |
| `search` | `string` | Tidak | Cari berdasarkan nomor atau deskripsi tiket |

#### Contoh Request

```
GET /api/lite/tickets?page=1&per_page=20&status=open&search=login
Authorization: Bearer <token>
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": [
    {
      "ticket_id": 101,
      "ticket_number": "TKT-2024-001",
      "description": "Tidak bisa login ke sistem",
      "ticket_priority": "High",
      "ticket_type": "Incident",
      "status": "open",
      "start_date": "2024-06-15",
      "end_date": null,
      "last_message_at": "2024-06-15T12:00:00.000000Z",
      "created_at": "2024-06-15T10:30:00.000000Z",
      "updated_at": "2024-06-15T12:00:00.000000Z",
      "customer": {
        "customer_id": 5,
        "customer_name": "PT. ABC Indonesia",
        "customer_code": "ABC001"
      },
      "pic": {
        "employee_id": 12,
        "employee_name": "Jane"
      },
      "members": [
        { "employee_id": 15, "employee_name": "Bob" }
      ],
      "sla": {
        "resolution_status": "pending",
        "resolution_due_at": "2024-06-17T10:30:00.000000Z",
        "response_status": "met"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

#### Perilaku Berdasarkan Role

| Role | Tiket yang Ditampilkan |
|------|----------------------|
| EC Administrator | Semua tiket |
| EC User | Semua tiket |
| Delivery Support Head | Semua tiket |
| Delivery Helpdesk | Semua tiket |
| Delivery Support Manager | Semua tiket |
| Delivery Support User | Hanya tiket yang belum di-assign (unassigned) |
| External Employee | Hanya tiket di mana dia adalah PIC atau anggota |

---

### 5.2 GET /tickets/statistics

Mengambil ringkasan jumlah tiket per status.

**Method:** `GET`  
**URL:** `/api/lite/tickets/statistics`  
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": {
    "total": 450,
    "open": 40,
    "inprocess": 60,
    "waiting_on_customer": 15,
    "waiting_on_3rd_party": 8,
    "waiting_to_confirmation": 5,
    "hold": 10,
    "cancelled": 20,
    "closed": 292
  }
}
```

---

### 5.3 GET /tickets/{id}

Mengambil detail lengkap satu tiket berdasarkan ID.

**Method:** `GET`  
**URL:** `/api/lite/tickets/{id}`  
**Auth:** Diperlukan

#### Path Parameters

| Parameter | Type | Deskripsi |
|-----------|------|-----------|
| `id` | `integer` | ID tiket |

#### Contoh Request

```
GET /api/lite/tickets/101
Authorization: Bearer <token>
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": {
    "ticket_id": 101,
    "ticket_number": "TKT-2024-001",
    "description": "Tidak bisa login ke sistem",
    "ticket_priority": "High",
    "ticket_type": "Incident",
    "status": "open",
    "scale": "Small",
    "channel": "web",
    "man_days": 2,
    "progress_percentage": 30.0,
    "wait_close": false,
    "start_date": "2024-06-15",
    "end_date": null,
    "last_message_at": "2024-06-15T12:00:00.000000Z",
    "last_customer_reply_at": "2024-06-15T11:00:00.000000Z",
    "last_agent_reply_at": "2024-06-15T12:00:00.000000Z",
    "created_at": "2024-06-15T10:30:00.000000Z",
    "updated_at": "2024-06-15T12:00:00.000000Z",
    "customer": {
      "customer_id": 5,
      "customer_name": "PT. ABC Indonesia",
      "customer_code": "ABC001"
    },
    "end_customer_id": null,
    "end_customer_name": null,
    "pic": {
      "employee_id": 12,
      "employee_name": "Jane"
    },
    "members": [
      { "employee_id": 15, "employee_name": "Bob" }
    ],
    "sla": {
      "resolution_status": "pending",
      "resolution_due_at": "2024-06-17T10:30:00.000000Z",
      "response_status": "met"
    },
    "sla_detail": {
      "target_response_hours": 4,
      "response_time_hours": 1.5,
      "response_status": "met",
      "target_resolution_hours": 48,
      "resolution_due_at": "2024-06-17T10:30:00.000000Z",
      "resolution_time_hours": null,
      "resolution_status": "pending"
    }
  }
}
```

#### Contoh Response Error (404)

```json
{
  "success": false,
  "message": "Ticket not found."
}
```

---

### 5.4 PATCH /tickets/{id}/status

Mengubah status tiket. Hanya role tertentu yang diizinkan.

**Method:** `PATCH`  
**URL:** `/api/lite/tickets/{id}/status`  
**Auth:** Diperlukan

**Role yang diizinkan:** EC Administrator, Delivery Support Head, Delivery Helpdesk, Delivery RPMO Head, Delivery Support Manager

#### Path Parameters

| Parameter | Type | Deskripsi |
|-----------|------|-----------|
| `id` | `integer` | ID tiket |

#### Request Body

| Field | Type | Wajib | Deskripsi |
|-------|------|-------|-----------|
| `status` | `string` | Ya | Status baru tiket (lihat enum di bagian 8) |

#### Contoh Request

```json
{
  "status": "inprocess"
}
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "message": "Ticket status updated successfully.",
  "data": {
    "ticket_id": 101,
    "old_status": "open",
    "new_status": "inprocess"
  }
}
```

#### Contoh Response Error (403)

```json
{
  "success": false,
  "message": "You do not have permission to update ticket status."
}
```

---

### 5.5 GET /tickets/{ticketId}/messages

Mengambil semua pesan (percakapan) untuk satu tiket.

**Method:** `GET`  
**URL:** `/api/lite/tickets/{ticketId}/messages`  
**Auth:** Diperlukan

#### Path Parameters

| Parameter | Type | Deskripsi |
|-----------|------|-----------|
| `ticketId` | `integer` | ID tiket |

#### Query Parameters

| Parameter | Type | Deskripsi |
|-----------|------|-----------|
| `highlight_message_id` | `integer` | Opsional. Isi dengan `message_id` dari notifikasi (mis. saat user di-tag di internal note) agar backend menandai bubble pesan tsb dengan `is_highlighted: true`, sehingga frontend tinggal scroll ke pesan yang flag-nya `true` tanpa perlu mencari sendiri di array. Diabaikan (jadi `null`) jika pesan tidak ditemukan di tiket ini. |

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 500,
      "ticket_id": 101,
      "sender_type": "employee",
      "sender_id": 12,
      "sender_name": "Jane Smith",
      "sender_email": "jane@company.com",
      "message_body": "Selamat siang, kami sedang menginvestigasi masalah ini.",
      "message_html": "<p>Selamat siang, kami sedang menginvestigasi masalah ini.</p>",
      "message_type": "reply",
      "reply_to_id": null,
      "reply_to_preview": null,
      "channel": "web",
      "is_read_by_customer": true,
      "is_read_by_agent": true,
      "is_deleted": false,
      "is_highlighted": false,
      "attachments": [],
      "created_at": "2024-06-15T11:00:00.000000Z"
    },
    {
      "id": 501,
      "ticket_id": 101,
      "sender_type": "employee",
      "sender_id": 12,
      "sender_name": "Jane Smith",
      "sender_email": "jane@company.com",
      "message_body": "Catatan internal: perlu eskalasi ke tim database.",
      "message_html": "<p>Catatan internal: perlu eskalasi ke tim database.</p>",
      "message_type": "internal_note",
      "reply_to_id": null,
      "reply_to_preview": null,
      "channel": "web",
      "is_read_by_customer": false,
      "is_read_by_agent": true,
      "is_deleted": false,
      "is_highlighted": true,
      "attachments": [],
      "created_at": "2024-06-15T12:00:00.000000Z"
    }
  ],
  "meta": {
    "highlight_message_id": 501
  }
}
```

**Alur pemakaian dari notifikasi:**
1. `GET /notifications` mengembalikan tiap item dengan `ticket_id` dan `message_id`.
2. Saat item notifikasi di-tap, frontend navigasi ke halaman tiket lalu memanggil
   `GET /tickets/{ticket_id}/messages?highlight_message_id={message_id}`.
3. Render daftar pesan, lalu scroll otomatis ke item dengan `is_highlighted: true`
   (opsional beri efek highlight sesaat pada bubble tsb).

---

### 5.6 POST /tickets/{ticketId}/messages

Menambah pesan baru ke tiket (reply atau internal note).

**Method:** `POST`  
**URL:** `/api/lite/tickets/{ticketId}/messages`  
**Auth:** Diperlukan

#### Path Parameters

| Parameter | Type | Deskripsi |
|-----------|------|-----------|
| `ticketId` | `integer` | ID tiket |

#### Request Body

| Field | Type | Wajib | Deskripsi |
|-------|------|-------|-----------|
| `message` | `string` | Ya | Isi pesan (HTML diperbolehkan) |
| `message_type` | `string` | Tidak | `reply` (default) atau `internal_note` |
| `is_internal_note` | `boolean` | Tidak | `true` untuk internal note (alternatif dari `message_type`) |
| `reply_to_id` | `integer` | Tidak | ID pesan yang dibalas |

#### Contoh Request

```json
{
  "message": "<p>Masalah telah diselesaikan. Silakan coba login kembali.</p>",
  "message_type": "reply",
  "reply_to_id": null
}
```

#### Contoh Response Sukses (201)

```json
{
  "success": true,
  "message": "Message sent successfully.",
  "data": {
    "id": 502,
    "ticket_id": 101,
    "sender_type": "employee",
    "sender_id": 12,
    "sender_name": "Jane Smith",
    "sender_email": "jane@company.com",
    "message_body": "Masalah telah diselesaikan. Silakan coba login kembali.",
    "message_html": "<p>Masalah telah diselesaikan. Silakan coba login kembali.</p>",
    "message_type": "reply",
    "reply_to_id": null,
    "reply_to_preview": null,
    "channel": "web",
    "is_read_by_customer": false,
    "is_read_by_agent": true,
    "is_deleted": false,
    "attachments": [],
    "created_at": "2024-06-15T13:00:00.000000Z"
  }
}
```

> **Catatan:** Endpoint ini mengirim pesan via sistem internal (web), **tidak** mengirim email. Integrasi email tidak tersedia di Lite API.

---

### 5.7 GET /tickets/my

Mengambil daftar tiket di mana user yang sedang login adalah **PIC** (`ticket_lead_id`) **atau anggota** (ada di `ticket_member`). Berlaku untuk semua role.

> **Konsistensi data:** `meta.total` yang dikembalikan endpoint ini identik dengan `data.ticket_stats.total` di `GET /dashboard` untuk Delivery Support User, karena keduanya menggunakan scope yang sama.

**Method:** `GET`  
**URL:** `/api/lite/tickets/my`  
**Auth:** Diperlukan  
**Role yang dapat mengakses:** Semua role

#### Request Headers

| Header | Value | Wajib |
|--------|-------|-------|
| Authorization | `Bearer <token>` | Ya (jika tidak pakai cookie) |

#### Query Parameters

| Parameter | Type | Wajib | Deskripsi |
|-----------|------|-------|-----------|
| `page` | `integer` | Tidak | Nomor halaman (default: 1) |
| `per_page` | `integer` | Tidak | Jumlah per halaman (default: 20, max: 100) |
| `status` | `string` | Tidak | Filter berdasarkan status tiket |
| `priority` | `string` | Tidak | Filter berdasarkan prioritas |
| `search` | `string` | Tidak | Cari berdasarkan nomor atau deskripsi tiket |

#### Contoh Request

```
GET /api/lite/tickets/my?page=1&per_page=20&status=inprocess
Authorization: Bearer <token>
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": [
    {
      "ticket_id": 101,
      "ticket_number": "TKT-2024-001",
      "description": "Tidak bisa login ke sistem",
      "ticket_priority": "High",
      "ticket_type": "Incident",
      "status": "inprocess",
      "start_date": "2024-06-15",
      "end_date": null,
      "last_message_at": "2024-06-15T12:00:00.000000Z",
      "created_at": "2024-06-15T10:30:00.000000Z",
      "updated_at": "2024-06-15T12:00:00.000000Z",
      "customer": {
        "customer_id": 5,
        "customer_name": "PT. ABC Indonesia",
        "customer_code": "ABC001"
      },
      "pic": {
        "employee_id": 42,
        "employee_name": "John"
      },
      "members": [
        { "employee_id": 15, "employee_name": "Bob" }
      ],
      "sla": {
        "resolution_status": "pending",
        "resolution_due_at": "2024-06-17T10:30:00.000000Z",
        "response_status": "met"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 12,
    "last_page": 1
  }
}
```

> **Catatan:** Struktur setiap item tiket identik dengan respons `GET /tickets`. `meta.total` pada endpoint ini konsisten dengan `ticket_stats.total` di `GET /dashboard`.

---

## 6. Endpoint Profil

### 6.1 GET /profile

Mengambil data profil lengkap user yang sedang login.

**Method:** `GET`  
**URL:** `/api/lite/profile`  
**Auth:** Diperlukan

#### Contoh Request

```
GET /api/lite/profile
Authorization: Bearer <token>
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": {
    "id": 42,
    "eci": "ECI017",
    "is_active": true,
    "title": "Mr.",
    "first_name": "John",
    "last_name": "Doe",
    "nick_name": "John",
    "gender": "Male",
    "religion": "Islam",
    "marital_status": "Married",
    "birth_date": "1990-05-15",
    "birth_place": "Jakarta",
    "since_date": "2020-01-01",
    "personnel_area": "Head Office",
    "employee_group": "Permanent",
    "employee_subgroup": "IT Department",
    "position": "Senior Consultant",
    "division": "Delivery",
    "department": "IT Services",
    "direct_supervision": "Manager A",
    "manager": "Director B",
    "employee_type": "Internal",
    "country": "ID",
    "region": "DKI Jakarta",
    "city": "Jakarta Selatan",
    "district": "Kebayoran Baru",
    "street": "Jl. Sudirman No. 1",
    "postal_code": "12190",
    "cell_phone": "081234567890",
    "telephone": null,
    "email_personal": "john.personal@gmail.com",
    "email_work": "john.doe@company.com",
    "roles": [
      { "id": 2, "name": "Delivery Support User" }
    ]
  }
}
```

---

### 6.2 PATCH /profile/change-password

Mengubah password user yang sedang login.

**Method:** `PATCH`  
**URL:** `/api/lite/profile/change-password`  
**Auth:** Diperlukan

#### Request Body

| Field | Type | Wajib | Deskripsi |
|-------|------|-------|-----------|
| `password` | `string` | Ya | Password baru (min 8 karakter) |
| `password_confirmation` | `string` | Ya | Konfirmasi password baru (harus sama dengan `password`) |

#### Contoh Request

```json
{
  "password": "newSecurePassword123",
  "password_confirmation": "newSecurePassword123"
}
```

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "message": "Password changed successfully."
}
```

#### Contoh Response Error Validasi (422)

```json
{
  "success": false,
  "message": "Password update data is invalid.",
  "errors": {
    "password": ["The password confirmation does not match."],
    "password_confirmation": ["The password confirmation field is required."]
  }
}
```

---

## 7. Endpoint Notifikasi

Notifikasi bekerja sama persis dengan yang ada di web app (bell dropdown, badge unread, mark as read, delete) — sama-sama membaca tabel `notifications` yang sudah dipakai lintas modul (reply tiket, mention, perubahan member tiket, timesheet, mandays, late exception, reminder kontrak/invoice, dsb). Notifikasi baru otomatis dibuat oleh business logic yang bersangkutan; endpoint di bawah ini hanya untuk membaca dan mengelola status baca/hapus.

> **Catatan:** Web Push (notifikasi ke browser saat ada notifikasi baru) berjalan otomatis dan independen dari endpoint ini — tidak perlu subscribe apa pun dari Lite app. Untuk update real-time di UI, lakukan polling ke `GET /notifications/unread-count` secara berkala (misal setiap 30–60 detik).

### 7.1 GET /notifications

Mengambil 20 notifikasi terbaru milik user yang sedang login, beserta jumlah yang belum dibaca — untuk bell dropdown.

**Method:** `GET`
**URL:** `/api/lite/notifications`
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "data": [
    {
      "id": 901,
      "type": "ticket_reply",
      "ticket_id": 101,
      "ticket_number": "TKT-2024-001",
      "customer_name": "ABC001",
      "message_id": 502,
      "from_name": "Jane Smith",
      "preview": "Masalah telah diselesaikan. Silakan coba login kembali.",
      "link": "/ticket/101#msg-502",
      "is_read": false,
      "created_at": "5 minutes ago"
    }
  ],
  "unread_count": 3
}
```

---

### 7.2 GET /notifications/unread-count

Endpoint ringan khusus untuk polling badge notifikasi (tanpa mengambil isi list).

**Method:** `GET`
**URL:** `/api/lite/notifications/unread-count`
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{
  "success": true,
  "count": 3,
  "message_sound_count": 1
}
```

> `message_sound_count` adalah subset dari `count` khusus tipe `ticket_reply` / `ticket_internal_note` — dipakai jika ingin membedakan bunyi notifikasi chat dari notifikasi umum lainnya.

---

### 7.3 PUT /notifications/{id}/read

Menandai satu notifikasi sebagai sudah dibaca.

**Method:** `PUT`
**URL:** `/api/lite/notifications/{id}/read`
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{ "success": true }
```

#### Contoh Response Error (404)

```json
{ "success": false, "message": "Notification not found." }
```

---

### 7.4 PUT /notifications/read-all

Menandai semua notifikasi milik user yang login sebagai sudah dibaca.

**Method:** `PUT`
**URL:** `/api/lite/notifications/read-all`
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{ "success": true }
```

---

### 7.5 DELETE /notifications/{id}

Menghapus satu notifikasi.

**Method:** `DELETE`
**URL:** `/api/lite/notifications/{id}`
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{ "success": true }
```

---

### 7.6 DELETE /notifications/bulk-delete

Menghapus semua notifikasi yang **sudah dibaca** milik user yang login.

**Method:** `DELETE`
**URL:** `/api/lite/notifications/bulk-delete`
**Auth:** Diperlukan

#### Contoh Response Sukses (200)

```json
{ "success": true }
```

---

## 8. Kode Status HTTP

| Kode | Deskripsi |
|------|-----------|
| `200` | OK — Request berhasil |
| `201` | Created — Resource berhasil dibuat |
| `401` | Unauthorized — Token tidak valid atau belum login |
| `403` | Forbidden — Tidak memiliki izin |
| `404` | Not Found — Resource tidak ditemukan |
| `422` | Unprocessable Entity — Validasi input gagal |
| `429` | Too Many Requests — Rate limit tercapai |
| `500` | Internal Server Error — Terjadi kesalahan pada server |

---

## 9. Daftar Nilai Enum

### Status Tiket

| Value | Deskripsi |
|-------|-----------|
| `open` | Tiket baru, belum ditangani |
| `inprocess` | Sedang dalam proses penanganan |
| `waiting_on_customer` | Menunggu respons dari customer |
| `waiting_on_3rd_party` | Menunggu pihak ketiga |
| `waiting_to_confirmation` | Menunggu konfirmasi penyelesaian |
| `hold` | Ditahan sementara |
| `cancelled` | Dibatalkan |
| `closed` | Selesai dan ditutup |

### Prioritas Tiket

| Value | Deskripsi |
|-------|-----------|
| `Very High` | Sangat mendesak |
| `High` | Mendesak |
| `Medium` | Normal |
| `Low` | Tidak mendesak |

### Jenis Pesan (message_type)

| Value | Deskripsi |
|-------|-----------|
| `reply` | Balasan yang terlihat oleh semua pihak |
| `internal_note` | Catatan internal, hanya terlihat oleh tim internal |

### Role ID

| ID | Nama |
|----|------|
| `1` | EC Administrator |
| `2` | Delivery Support User |
| `3` | EC User |
| `4` | Delivery Project Head |
| `5` | Delivery Support Head |
| `6` | Delivery Helpdesk |
| `7` | Delivery RPMO Head |
| `12` | Delivery Project Administrator |
| `14` | Delivery Support Manager |
| `15` | Delivery Project User |

### SLA Status

| Value | Deskripsi |
|-------|-----------|
| `pending` | SLA masih berjalan |
| `met` | SLA terpenuhi (tepat waktu) |
| `breached` | SLA dilanggar (melewati deadline) |
| `paused` | SLA sedang dijeda (misal: sedang meeting) |

---

## 10. Catatan Penting

### Token & Keamanan

1. **Token berlaku 24 jam** sejak waktu login. Setelah expired, user harus login ulang.
2. **Jangan simpan token di cookie yang dapat diakses JavaScript** (gunakan `httpOnly` cookie atau memory).
3. **Rate limit login** adalah **5 request per menit per IP** untuk mencegah brute force.
4. Jika menerima response `401`, selalu arahkan user ke halaman login dan hapus token yang tersimpan.

### Scope Data per Role

5. Data yang ditampilkan di `/tickets` dan `/dashboard` **disesuaikan otomatis** berdasarkan role user yang login — tidak perlu mengirim parameter role tambahan.
6. **Delivery Support User** hanya melihat tiket yang belum di-assign (unassigned) di `GET /tickets`. Untuk melihat tiket yang dia tangani sendiri (sebagai PIC atau member), gunakan `GET /tickets/my`. Endpoint ini tersedia untuk semua role.

### Pesan (Messages)

7. Endpoint `POST /tickets/{ticketId}/messages` **hanya mendukung pengiriman pesan via web** (channel: `web`). Pengiriman email terintegrasi tidak tersedia di Lite API.
8. `internal_note` hanya terlihat oleh tim internal dan **tidak akan ditampilkan kepada customer**.

### Password

9. Setelah berhasil `changePassword`, **token yang ada tetap valid** (tidak di-revoke otomatis). Pertimbangkan untuk melakukan logout manual jika ingin memastikan session baru.
10. Akun baru yang belum pernah login (`require_password_change: true`) **tidak mendapatkan token** sampai mereka menyelesaikan setup password via email.

### Error Handling

11. Selalu periksa field `success` pada setiap respons (`true` / `false`) sebelum menggunakan data.
12. Untuk error `422`, gunakan field `errors` untuk menampilkan pesan validasi per field kepada user.
13. Untuk error `500`, tampilkan pesan umum kepada user dan log detail error untuk debugging.

---

*Dokumentasi ini dibuat berdasarkan implementasi EcoSystem Lite API v1.0. Untuk pertanyaan lebih lanjut, hubungi tim backend.*

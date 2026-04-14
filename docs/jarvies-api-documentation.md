# JARVIES API Documentation

Dokumentasi lengkap seluruh API yang tersedia di JARVIES untuk keperluan integrasi dan testing Postman.

**Base URL (local):** `http://127.0.0.1:8001`
**Format response:** JSON
**Content-Type default:** `application/json`

---

## Daftar Isi

1. [Autentikasi](#1-autentikasi)
2. [Dashboard](#2-dashboard)
3. [Tiket](#3-tiket)
4. [Cara Setup Postman](#4-cara-setup-postman)

---

## 1. Autentikasi

### Token Format

JARVIES menggunakan **Bearer token stateless** (bukan JWT/Sanctum).

```
access_token  = base64("{customer_code}|{timestamp}|customer")
refresh_token = hex string 64 karakter (disimpan di tabel api_refresh_tokens, expire 90 hari)
```

Kirim di setiap request protected:
```
Authorization: Bearer {access_token}
```

---

### POST /api/auth/login

Login customer. Mendapatkan access_token dan refresh_token.

**Request Body (JSON):**
```json
{
    "email": "customer@domain.com",
    "password": "password123"
}
```
> `email` bisa diisi dengan email, username, atau nomor HP.

**Response 200 — Berhasil:**
```json
{
    "success": true,
    "message": "Login berhasil.",
    "data": {
        "access_token": "Q1VTVC0wMDF8MTcwMDAwMDAwMHxjdXN0b21lcg==",
        "refresh_token": "a1b2c3d4e5f6...",
        "token_type": "Bearer",
        "user": {
            "id": 37,
            "type": "customer",
            "customer_code": "CUST-001",
            "company_name": "PT Contoh Perusahaan",
            "email": "customer@domain.com",
            "category": "Enterprise",
            "group": "A"
        }
    }
}
```

**Response 401 — Password salah:**
```json
{
    "success": false,
    "message": "Email atau password salah."
}
```

**Response 403 — Belum set password:**
```json
{
    "success": false,
    "require_password_change": true,
    "message": "Silakan cek email Anda untuk mengatur password terlebih dahulu.",
    "email": "cu***@domain.com"
}
```

---

### POST /api/auth/refresh

Tukar refresh token dengan access token baru (rotating — token lama langsung hangus).

**Request Body (JSON):**
```json
{
    "refresh_token": "a1b2c3d4e5f6..."
}
```

**Response 200:**
```json
{
    "success": true,
    "data": {
        "access_token": "bmV3dG9rZW58MTcwMDAwMDAwMHxjdXN0b21lcg==",
        "refresh_token": "newrefreshtoken...",
        "expires_in": 604800,
        "token_type": "Bearer"
    }
}
```

**Response 401 — Token tidak valid/expire:**
```json
{
    "success": false,
    "message": "Refresh token tidak valid atau sudah kedaluwarsa. Silakan login ulang.",
    "code": "REFRESH_TOKEN_INVALID"
}
```

---

### GET /api/auth/me 🔒

Profil customer yang sedang login.

**Headers:** `Authorization: Bearer {access_token}`

**Response 200:**
```json
{
    "success": true,
    "data": {
        "id": 37,
        "type": "customer",
        "customer_code": "CUST-001",
        "company_name": "PT Contoh Perusahaan",
        "email": "customer@domain.com",
        "category": "Enterprise",
        "group": "A"
    }
}
```

---

### POST /api/auth/logout 🔒

Logout dan hapus refresh token.

**Headers:** `Authorization: Bearer {access_token}`

**Request Body (JSON, opsional):**
```json
{
    "refresh_token": "a1b2c3d4e5f6..."
}
```
> Jika `refresh_token` tidak dikirim, semua refresh token customer ini (semua device) akan dihapus.

**Response 200:**
```json
{
    "success": true,
    "message": "Logout berhasil."
}
```

---

## 2. Dashboard

### GET /api/dashboard 🔒

Ringkasan statistik tiket + tiket terbaru untuk halaman Home.

**Headers:** `Authorization: Bearer {access_token}`

**Response 200:**
```json
{
    "success": true,
    "data": {
        "summary": {
            "total": 15,
            "open": 3,
            "in_progress": 5,
            "hold": 1,
            "closed": 5,
            "cancel": 1
        },
        "unread_messages": 2,
        "recent_tickets": [
            {
                "ticket_id": 101,
                "ticket_number": "TCK-2024-001",
                "description": "Masalah tcode VL03",
                "status": "open",
                "status_label": "Open",
                "ticket_priority": "High",
                "priority_color": "#ef4444",
                "employee_name": "Budi",
                "created_at": "2024-01-15T08:00:00.000000Z"
            }
        ]
    }
}
```

---

## 3. Tiket

### GET /api/tickets 🔒

List semua tiket milik customer.

**Headers:** `Authorization: Bearer {access_token}`

**Query Params (opsional):**

| Param | Nilai | Keterangan |
|---|---|---|
| `status` | `open` `in_progress` `hold` `closed` `cancel` | Filter berdasarkan status |

**Contoh:** `GET /api/tickets?status=open`

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "ticket_id": 101,
            "ticket_number": "TCK-2024-001",
            "description": "Masalah tcode VL03",
            "status": "open",
            "status_label": "Open",
            "ticket_priority": "High",
            "priority_color": "#ef4444",
            "start_date": null,
            "end_date": null,
            "man_days": null,
            "employee": {
                "employee_id": 5,
                "employee_name": "Budi"
            },
            "members": [],
            "created_at": "2024-01-15T08:00:00.000000Z",
            "updated_at": "2024-01-15T10:00:00.000000Z"
        }
    ]
}
```

---

### GET /api/tickets/{id} 🔒

Detail satu tiket. Customer hanya bisa mengakses tiket miliknya.

**Headers:** `Authorization: Bearer {access_token}`

**Response 200:**
```json
{
    "success": true,
    "data": {
        "ticket_id": 101,
        "ticket_number": "TCK-2024-001",
        "description": "Masalah tcode VL03",
        "status": "open",
        "status_label": "Open",
        "jarvies_status": "in process",
        "ticket_priority": "High",
        "priority_color": "#ef4444",
        "channel": "web",
        "wait_close": null,
        "start_date": null,
        "end_date": null,
        "man_days": null,
        "employee": {
            "employee_id": 5,
            "employee_name": "Budi"
        },
        "members": [],
        "created_at": "2024-01-15T08:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
    }
}
```

**Response 404:**
```json
{
    "success": false,
    "message": "Tiket tidak ditemukan."
}
```

---

### POST /api/tickets 🔒

Buat tiket baru. Tiket masuk ke **staging** terlebih dahulu, menunggu validasi admin.

**Headers:** `Authorization: Bearer {access_token}`

**Request Body (JSON):**
```json
{
    "description": "Masalah pada tcode VL03 tidak bisa dibuka",
    "ticket_priority": "High",
    "body": "Detail masalah lebih lengkap di sini..."
}
```

| Field | Tipe | Wajib | Nilai |
|---|---|---|---|
| `description` | string | Ya | Subject/judul tiket |
| `ticket_priority` | string | Tidak | `Low` `Medium` `High` |
| `body` | string | Tidak | Isi pesan awal |

**Response 201 — Berhasil:**
```json
{
    "success": true,
    "message": "Tiket berhasil dikirim dan sedang menunggu validasi admin.",
    "data": {
        "id": 42,
        "staging_ref": "STG-42",
        "description": "Masalah pada tcode VL03 tidak bisa dibuka",
        "ticket_priority": "High",
        "status": "unvalidated",
        "status_label": "Menunggu Validasi",
        "created_at": "2024-01-15T08:00:00.000000Z"
    }
}
```

**Response 422 — Validasi gagal:**
```json
{
    "success": false,
    "message": "Validasi gagal.",
    "errors": {
        "description": ["The description field is required."]
    }
}
```

---

### GET /api/tickets/staging 🔒

List staging tiket milik customer (tiket yang belum/sudah divalidasi admin).

**Headers:** `Authorization: Bearer {access_token}`

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 42,
            "staging_ref": "STG-42",
            "description": "Masalah pada tcode VL03",
            "ticket_priority": "High",
            "status": "unvalidated",
            "status_label": "Menunggu Validasi",
            "rejection_reason": null,
            "ticket_id": null,
            "ticket_number": null,
            "created_at": "2024-01-15T08:00:00.000000Z",
            "validated_at": null
        }
    ]
}
```

> **Nilai status:** `unvalidated` (menunggu) | `validated` (disetujui) | `rejected` (ditolak)

---

### GET /api/tickets/{id}/messages 🔒

List pesan percakapan tiket. Internal note tidak ditampilkan.
Otomatis menandai pesan dari agent sebagai sudah dibaca.

**Headers:** `Authorization: Bearer {access_token}`

**Response 200:**
```json
{
    "success": true,
    "data": [
        {
            "id": 201,
            "sender_type": "employee",
            "sender_name": "Budi (Support)",
            "message": "Halo, kami sedang meninjau masalah Anda.",
            "attachments": [],
            "created_at": "2024-01-15T09:00:00.000000Z"
        },
        {
            "id": 202,
            "sender_type": "customer",
            "sender_name": "PT Contoh Perusahaan",
            "message": "Terima kasih, mohon segera ditangani.",
            "attachments": [
                {
                    "id": 10,
                    "file_name": "screenshot.png",
                    "type": "image",
                    "url": "https://..."
                }
            ],
            "created_at": "2024-01-15T09:30:00.000000Z"
        }
    ]
}
```

> **sender_type:** `employee` (dari agent/admin) | `customer` (dari customer)

---

### POST /api/tickets/{id}/messages 🔒

Kirim pesan/reply ke tiket.

**Headers:** `Authorization: Bearer {access_token}`

**Request Body (JSON):**
```json
{
    "message": "Masalah masih terjadi setelah restart, mohon bantuan lebih lanjut."
}
```

**Response 201 — Berhasil:**
```json
{
    "success": true,
    "message": "Pesan berhasil dikirim.",
    "data": {
        "id": 203,
        "sender_type": "customer",
        "sender_name": "PT Contoh Perusahaan",
        "message": "Masalah masih terjadi setelah restart, mohon bantuan lebih lanjut.",
        "created_at": "2024-01-15T10:00:00.000000Z"
    }
}
```

---

## 4. Cara Setup Postman

### A. Buat Environment

Buat environment baru dengan variabel berikut:

| Variable | Initial Value |
|---|---|
| `base_url` | `http://127.0.0.1:8001` |
| `access_token` | *(kosongkan, diisi otomatis setelah login)* |
| `refresh_token` | *(kosongkan, diisi otomatis setelah login)* |

---

### B. Auto-set Token setelah Login

Di request **POST /api/auth/login**, tab **Tests**, tambahkan script berikut:

```javascript
if (pm.response.code === 200) {
    const data = pm.response.json().data;
    pm.environment.set("access_token",  data.access_token);
    pm.environment.set("refresh_token", data.refresh_token);
    console.log("Token set:", data.access_token);
}
```

Setelah login, semua request lain tinggal pakai `{{access_token}}` di Authorization.

---

### C. Setup Authorization Global (Collection Level)

Di **Collection → Authorization**:
- Type: `Bearer Token`
- Token: `{{access_token}}`

Semua request dalam collection akan otomatis menggunakan token ini.

---

### D. Urutan Testing

```
1. POST /api/auth/login          → dapat access_token & refresh_token
2. GET  /api/auth/me             → verifikasi token valid
3. GET  /api/dashboard           → cek statistik tiket
4. GET  /api/tickets             → list semua tiket
5. POST /api/tickets             → buat tiket baru (masuk staging)
6. GET  /api/tickets/staging     → cek tiket staging yang baru dibuat
7. GET  /api/tickets/{id}        → detail tiket (pakai ticket_id dari langkah 4)
8. GET  /api/tickets/{id}/messages  → list pesan tiket
9. POST /api/tickets/{id}/messages  → kirim pesan balasan
10. POST /api/auth/refresh        → refresh access token
11. POST /api/auth/logout         → logout
```

---

### E. Contoh Request Headers

**Semua endpoint 🔒 wajib kirim:**
```
Authorization: Bearer {{access_token}}
Accept: application/json
Content-Type: application/json
```

---

### F. HTTP Status Code

| Code | Artinya |
|---|---|
| `200` | Berhasil |
| `201` | Berhasil membuat data baru |
| `401` | Token tidak valid / tidak ada |
| `403` | Tidak punya akses (bukan customer / belum set password) |
| `404` | Data tidak ditemukan |
| `422` | Validasi gagal |
| `500` | Error server |

---

## Catatan Penting untuk EcoSystem

1. **Token tidak expire** — access token JARVIES bersifat stateless dan tidak ada expiry. Yang expire adalah refresh token (90 hari).
2. **Hanya customer** — semua endpoint `/api/*` hanya bisa diakses role customer (role_id = 3).
3. **Database shared** — JARVIES dan EcoSystem berbagi database `ecosystem`. Tiket yang dibuat/diupdate di salah satu sistem akan langsung terlihat di sistem lain.
4. **Staging flow** — tiket dari mobile/web customer selalu masuk ke `staging_tickets` dulu. EcoSystem yang bertugas memvalidasi dan membuat tiket nyata di tabel `tickets`.
5. **Internal note** — pesan dengan `is_internal_note = true` tidak akan pernah tampil di API customer.

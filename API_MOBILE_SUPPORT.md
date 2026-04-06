# API Mobile — Fitur Support & Tiket

Dokumentasi REST API untuk fitur **Support / Bantuan** pada aplikasi mobile EcoSystem.
Base URL: `https://<your-domain>/api/mobile/employee`

---

## Daftar Isi

1. [Autentikasi](#autentikasi)
2. [Format Response Standar](#format-response-standar)
3. [Error Handling](#error-handling)
4. [Endpoints — Ticket](#endpoints--ticket)
   - [GET /tickets — List Tiket](#get-tickets--list-tiket)
   - [GET /tickets/stats — Statistik Tiket](#get-ticketsstats--statistik-tiket)
   - [GET /tickets/{id} — Detail Tiket](#get-ticketsid--detail-tiket)
   - [POST /tickets — Buat Tiket](#post-tickets--buat-tiket)
   - [PUT /tickets/{id}/status — Update Status](#put-ticketsidstatus--update-status)
   - [GET /tickets/{id}/messages — List Pesan](#get-ticketsidmessages--list-pesan)
   - [POST /tickets/{id}/messages — Kirim Pesan](#post-ticketsidmessages--kirim-pesan)
   - [POST /tickets/{id}/ownership — Ambil Kepemilikan](#post-ticketsidownership--ambil-kepemilikan)
   - [PUT /tickets/{id}/mandays — Update Mandays](#put-ticketsidmandays--update-mandays)
   - [POST /tickets/{id}/send-to-customer — Kirim ke Customer](#post-ticketsidsend-to-customer--kirim-ke-customer)
5. [Endpoints — Support Ticket (Project)](#endpoints--support-ticket-project)
   - [GET /support-tickets — List Support Ticket](#get-support-tickets--list-support-ticket)
   - [GET /support-tickets/{id} — Detail Support Ticket](#get-support-ticketsid--detail-support-ticket)
6. [Enum Values](#enum-values)
7. [Contoh Integrasi Mobile (Flutter/Dart)](#contoh-integrasi-mobile-flutterdart)

---

## Autentikasi

Semua endpoint memerlukan Bearer Token dari Sanctum.

```
Authorization: Bearer <access_token>
Content-Type: application/json
```

Token diperoleh dari endpoint login:

```
POST /api/mobile/employee/auth/login
```

---

## Format Response Standar

```json
{
  "success": true,
  "message": "string (opsional)",
  "data": {}
}
```

Error response:

```json
{
  "success": false,
  "message": "Pesan error",
  "errors": {
    "field": ["detail error"]
  }
}
```

---

## Error Handling

| HTTP Code | Kondisi |
|-----------|---------|
| `200` | OK |
| `201` | Created — resource berhasil dibuat |
| `401` | Unauthorized — token tidak valid / kadaluarsa |
| `404` | Not Found — resource tidak ditemukan |
| `422` | Unprocessable Entity — validasi gagal |
| `500` | Internal Server Error |

---

## Endpoints — Ticket

### GET /tickets — List Tiket

Mengambil daftar tiket dengan stats ringkasan dan pagination.

**URL:** `GET /api/mobile/employee/tickets`

**Query Parameters:**

| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `search` | string | - | Cari berdasarkan judul, deskripsi, atau nama customer |
| `status` | string | - | Filter status: `Open`, `In Progress`, `Hold`, `Reply`, `Closed` |
| `assigned_to_me` | boolean | - | `true` = hanya tiket yang di-assign ke user login |
| `page` | integer | - | Nomor halaman (default: 1, per halaman: 15) |

**Response 200:**

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
      "id": 1,
      "title": "Error login tidak bisa masuk",
      "status": "In Progress",
      "priority": "High",
      "customer": {
        "id": 3,
        "name": "PT Maju Bersama"
      },
      "assigned_user": {
        "id": 7,
        "name": "Budi Santoso"
      },
      "updated_at": "2026-04-01"
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

### GET /tickets/stats — Statistik Tiket

Mengambil jumlah tiket per status untuk stats row di TicketListScreen.

**URL:** `GET /api/mobile/employee/tickets/stats`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "total": 42,
    "open": 7,
    "in_progress": 10,
    "hold": 5,
    "reply": 3,
    "closed": 17
  }
}
```

---

### GET /tickets/{id} — Detail Tiket

Mengambil detail lengkap tiket termasuk anggota tim.

**URL:** `GET /api/mobile/employee/tickets/{id}`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Error login tidak bisa masuk",
    "description": "User melaporkan tidak bisa login sejak update v2.3.1",
    "status": "In Progress",
    "priority": "High",
    "type": "Bug",
    "jarvies_status": "Open",
    "customer": {
      "id": 3,
      "name": "PT Maju Bersama"
    },
    "assigned_user": {
      "id": 7,
      "name": "Budi Santoso"
    },
    "members": [
      { "id": 7, "name": "Budi Santoso" },
      { "id": 12, "name": "Rina Kusuma" }
    ],
    "created_at": "2026-03-20",
    "updated_at": "2026-04-01"
  }
}
```

---

### POST /tickets — Buat Tiket

Membuat tiket baru. Mendukung upload lampiran (multipart/form-data).

**URL:** `POST /api/mobile/employee/tickets`

**Content-Type:** `multipart/form-data`

**Request Body:**

| Field | Tipe | Wajib | Keterangan |
|-------|------|-------|------------|
| `title` | string | Ya | Judul tiket, maks 255 karakter |
| `description` | string | Ya | Deskripsi detail masalah |
| `type` | string | Ya | `Bug`, `Feature Request`, `Improvement`, `Question` |
| `priority` | string | Ya | `Low`, `Medium`, `High` |
| `attachment` | file | - | PDF, PNG, JPG/JPEG — maks 10 MB |

**Contoh Request:**

```
POST /api/mobile/employee/tickets
Content-Type: multipart/form-data

title=Error login tidak bisa masuk
description=User melaporkan tidak bisa login sejak update v2.3.1
type=Bug
priority=High
attachment=<file>
```

**Response 201:**

```json
{
  "success": true,
  "message": "Tiket berhasil dibuat.",
  "data": {
    "id": 15,
    "title": "Error login tidak bisa masuk",
    "description": "User melaporkan tidak bisa login sejak update v2.3.1",
    "status": "Open",
    "priority": "High",
    "type": "Bug",
    "jarvies_status": null,
    "customer": { "id": null, "name": null },
    "assigned_user": { "id": 7, "name": "Budi Santoso" },
    "members": [],
    "created_at": "2026-04-01",
    "updated_at": "2026-04-01"
  }
}
```

**Response 422 (validasi gagal):**

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

### PUT /tickets/{id}/status — Update Status

Mengubah status tiket.

**URL:** `PUT /api/mobile/employee/tickets/{id}/status`

**Content-Type:** `application/json`

**Request Body:**

```json
{
  "status": "In Progress"
}
```

**Nilai status yang valid:** `Open`, `In Progress`, `Hold`, `Reply`, `Closed`

**Response 200:**

```json
{
  "success": true,
  "message": "Status tiket berhasil diperbarui.",
  "data": {
    "id": 1,
    "status": "In Progress"
  }
}
```

---

### GET /tickets/{id}/messages — List Pesan

Mengambil semua pesan dalam thread tiket (diurutkan dari terlama).

**URL:** `GET /api/mobile/employee/tickets/{id}/messages`

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "sender": {
        "id": 3,
        "name": "Ahmad Fauzi"
      },
      "content": "Kami sudah mencoba restart server tapi masalah masih ada.",
      "is_from_team": false,
      "created_at": "2026-04-01T08:30:00+07:00"
    },
    {
      "id": 102,
      "sender": {
        "id": 7,
        "name": "Budi Santoso"
      },
      "content": "Baik, kami sedang investigasi. Mohon tunggu 30 menit.",
      "is_from_team": true,
      "created_at": "2026-04-01T09:00:00+07:00"
    }
  ]
}
```

> `is_from_team: true` → bubble kanan (tim internal)
> `is_from_team: false` → bubble kiri (customer)

---

### POST /tickets/{id}/messages — Kirim Pesan

Mengirim pesan baru ke thread tiket dari sisi employee/tim.

**URL:** `POST /api/mobile/employee/tickets/{id}/messages`

**Content-Type:** `application/json`

**Request Body:**

```json
{
  "content": "Sudah kami fix di versi 2.3.2, mohon coba update."
}
```

**Response 201:**

```json
{
  "success": true,
  "message": "Pesan berhasil dikirim.",
  "data": {
    "id": 103,
    "sender": {
      "id": 7,
      "name": "Budi Santoso"
    },
    "content": "Sudah kami fix di versi 2.3.2, mohon coba update.",
    "is_from_team": true,
    "created_at": "2026-04-01T10:15:00+07:00"
  }
}
```

---

### POST /tickets/{id}/ownership — Ambil Kepemilikan

Menetapkan user yang sedang login sebagai PIC (assigned_user) tiket.

**URL:** `POST /api/mobile/employee/tickets/{id}/ownership`

**Request Body:** _(kosong)_

**Response 200:**

```json
{
  "success": true,
  "message": "Tiket berhasil diambil alih.",
  "data": {
    "id": 1,
    "title": "Error login tidak bisa masuk",
    "assigned_user": {
      "id": 9,
      "name": "Siti Rahayu"
    }
    // ...field lainnya
  }
}
```

---

### PUT /tickets/{id}/mandays — Update Mandays

Memperbarui estimasi mandays untuk tiket.

**URL:** `PUT /api/mobile/employee/tickets/{id}/mandays`

**Content-Type:** `application/json`

**Request Body:**

```json
{
  "man_days": 2.5
}
```

**Response 200:**

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

### POST /tickets/{id}/send-to-customer — Kirim ke Customer

Mengubah status tiket menjadi `Reply` dan mencatat timestamp terakhir balasan tim.
Digunakan setelah tim memberikan balasan dan ingin memberitahu customer.

**URL:** `POST /api/mobile/employee/tickets/{id}/send-to-customer`

**Request Body:** _(kosong)_

**Response 200:**

```json
{
  "success": true,
  "message": "Notifikasi berhasil dikirim ke customer."
}
```

---

## Endpoints — Support Ticket (Project)

### GET /support-tickets — List Support Ticket

Mengambil daftar delivery support (tiket berbasis proyek) dengan pagination.

**URL:** `GET /api/mobile/employee/support-tickets`

**Query Parameters:**

| Parameter | Tipe | Wajib | Keterangan |
|-----------|------|-------|------------|
| `search` | string | - | Cari berdasarkan nama support atau nama customer |
| `status` | string | - | `In Process`, `Not Started`, `Closed` |
| `page` | integer | - | Nomor halaman (default: 1) |

**Response 200:**

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "title": "Implementasi Modul Payroll",
      "status": "In Process",
      "customer": {
        "id": 3,
        "name": "PT Maju Bersama"
      },
      "pic_user": {
        "id": 7,
        "name": "Budi Santoso"
      },
      "start_date": "2026-03-01",
      "end_date": "2026-04-30",
      "progress_percent": 0.45,
      "team_members": [
        { "id": 7, "name": "Budi Santoso" },
        { "id": 12, "name": "Rina Kusuma" }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "total": 18
  }
}
```

> `progress_percent` bernilai `0.0 – 1.0` (sudah dinormalisasi dari 0–100).

---

### GET /support-tickets/{id} — Detail Support Ticket

Mengambil detail lengkap satu support ticket.

**URL:** `GET /api/mobile/employee/support-tickets/{id}`

**Response 200:**

```json
{
  "success": true,
  "data": {
    "id": 5,
    "title": "Implementasi Modul Payroll",
    "status": "In Process",
    "customer": {
      "id": 3,
      "name": "PT Maju Bersama"
    },
    "pic_user": {
      "id": 7,
      "name": "Budi Santoso"
    },
    "start_date": "2026-03-01",
    "end_date": "2026-04-30",
    "progress_percent": 0.45,
    "team_members": [
      { "id": 7, "name": "Budi Santoso" },
      { "id": 12, "name": "Rina Kusuma" },
      { "id": 15, "name": "Dian Pratama" }
    ]
  }
}
```

---

## Enum Values

### Ticket — Status

| Nilai (mobile) | Nilai DB | Keterangan |
|---------------|----------|------------|
| `Open` | `open` | Tiket baru masuk |
| `In Progress` | `in_progress` | Sedang dikerjakan |
| `Hold` | `hold` | Ditahan / menunggu info |
| `Reply` | `reply` | Menunggu balasan customer |
| `Closed` | `closed` | Selesai / ditutup |

### Ticket — Priority

| Nilai | Keterangan |
|-------|------------|
| `Low` | Urgensi rendah |
| `Medium` | Urgensi sedang |
| `High` | Urgensi tinggi |

### Ticket — Type

| Nilai | Keterangan |
|-------|------------|
| `Bug` | Laporan bug |
| `Feature Request` | Permintaan fitur baru |
| `Improvement` | Peningkatan fitur |
| `Question` | Pertanyaan umum |

### Ticket — Jarvies Status

| Nilai | Keterangan |
|-------|------------|
| `Open` | Terbuka di Jarvies |
| `Hold` | Ditahan |
| `Author Action Required` | Butuh aksi dari pelapor |
| `Closed` | Ditutup di Jarvies |

### Support Ticket — Status

| Nilai (mobile) | Kondisi Progress |
|---------------|-----------------|
| `Not Started` | `progress = 0%` |
| `In Process` | `0% < progress < 100%` |
| `Closed` | `progress >= 100%` |

---

## Contoh Integrasi Mobile (Flutter/Dart)

### Setup HTTP Client

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class ApiClient {
  static const String baseUrl = 'https://your-domain.com/api/mobile/employee';
  final String token;

  ApiClient(this.token);

  Map<String, String> get headers => {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
}
```

### Contoh: List Tiket

```dart
Future<Map<String, dynamic>> getTickets({
  String? search,
  String? status,
  bool assignedToMe = false,
  int page = 1,
}) async {
  final uri = Uri.parse('$baseUrl/tickets').replace(queryParameters: {
    if (search != null && search.isNotEmpty) 'search': search,
    if (status != null) 'status': status,
    if (assignedToMe) 'assigned_to_me': 'true',
    'page': page.toString(),
  });

  final response = await http.get(uri, headers: headers);
  return jsonDecode(response.body);
}
```

### Contoh: Buat Tiket (dengan lampiran)

```dart
Future<Map<String, dynamic>> createTicket({
  required String title,
  required String description,
  required String type,
  required String priority,
  String? attachmentPath,
}) async {
  final request = http.MultipartRequest(
    'POST',
    Uri.parse('$baseUrl/tickets'),
  );

  request.headers['Authorization'] = 'Bearer $token';
  request.fields['title'] = title;
  request.fields['description'] = description;
  request.fields['type'] = type;
  request.fields['priority'] = priority;

  if (attachmentPath != null) {
    request.files.add(await http.MultipartFile.fromPath(
      'attachment',
      attachmentPath,
    ));
  }

  final streamedResponse = await request.send();
  final response = await http.Response.fromStream(streamedResponse);
  return jsonDecode(response.body);
}
```

### Contoh: Kirim Pesan

```dart
Future<Map<String, dynamic>> sendMessage(int ticketId, String content) async {
  final response = await http.post(
    Uri.parse('$baseUrl/tickets/$ticketId/messages'),
    headers: headers,
    body: jsonEncode({'content': content}),
  );
  return jsonDecode(response.body);
}
```

### Contoh: Update Status

```dart
Future<Map<String, dynamic>> updateTicketStatus(int ticketId, String status) async {
  final response = await http.put(
    Uri.parse('$baseUrl/tickets/$ticketId/status'),
    headers: headers,
    body: jsonEncode({'status': status}),
  );
  return jsonDecode(response.body);
}
```

### Contoh: Stats

```dart
Future<Map<String, dynamic>> getTicketStats() async {
  final response = await http.get(
    Uri.parse('$baseUrl/tickets/stats'),
    headers: headers,
  );
  return jsonDecode(response.body);
}
```

---

## Ringkasan Endpoint

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| `GET` | `/tickets` | List tiket + stats ringkasan |
| `GET` | `/tickets/stats` | Statistik jumlah per status |
| `GET` | `/tickets/{id}` | Detail tiket |
| `POST` | `/tickets` | Buat tiket baru |
| `PUT` | `/tickets/{id}/status` | Update status tiket |
| `GET` | `/tickets/{id}/messages` | List pesan/chat |
| `POST` | `/tickets/{id}/messages` | Kirim pesan |
| `POST` | `/tickets/{id}/ownership` | Ambil alih tiket |
| `PUT` | `/tickets/{id}/mandays` | Update estimasi mandays |
| `POST` | `/tickets/{id}/send-to-customer` | Kirim notifikasi ke customer |
| `GET` | `/support-tickets` | List support ticket (project) |
| `GET` | `/support-tickets/{id}` | Detail support ticket |

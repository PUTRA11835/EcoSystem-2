# API Dokumentasi — Delivery Project (Mobile Employee)

> Versi: 1.0 | Backend: Laravel | Digunakan oleh: Aplikasi Mobile Employee

---

## Base URL & Auth

```
Base URL : https://<domain>/api/mobile/employee
Auth     : Authorization: Bearer <access_token>
```

Semua endpoint di bawah ini memerlukan header:

```
Authorization: Bearer <access_token>
Content-Type: application/json
```

---

## Ringkasan Endpoint

| Method | Endpoint | Deskripsi | Layar |
|--------|----------|-----------|-------|
| GET | `/projects` | List semua project dengan pagination | ProjectListScreen |
| GET | `/projects/{id}` | Detail satu project (phases + team + updates) | ProjectDetailScreen |
| POST | `/projects/{id}/updates` | Tambah catatan/update ke project | Tab Updates |

---

---

## 1. List Project

### `GET /projects`

Mengembalikan daftar project dengan pagination, filter status, dan pencarian.

### Query Parameters

| Parameter | Tipe | Required | Deskripsi |
|-----------|------|----------|-----------|
| `search` | string | No | Cari berdasarkan nama customer (`name_1`) atau `description` |
| `status` | string | No | Filter: `Open` \| `In Process` \| `Closed` |
| `page` | integer | No | Halaman (default: 1, per halaman: 15) |

### Response `200 OK`

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "customer": {
        "id": 1,
        "name": "PT. Abadi Jaya"
      },
      "project_type": "Implementation",
      "description": "ERP full implementation phase 1 including HR and Finance modules.",
      "category_status": "In Process",
      "track_status": "On Track",
      "pic_user": {
        "id": 3,
        "name": "John Doe"
      },
      "team_members": [
        { "id": 3, "name": "John Doe" },
        { "id": 5, "name": "Jane Smith" },
        { "id": 7, "name": "Bob Wilson" }
      ],
      "progress_percent": 0.65,
      "start_date": "2026-01-01",
      "end_date": "2026-06-30"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "total": 25
  }
}
```

### Penjelasan Field Response

| Field | Tipe | Sumber DB | Deskripsi |
|-------|------|-----------|-----------|
| `id` | integer | `delivery_projects.id` | ID project |
| `customer.id` | integer | `customers.customer_id` | ID customer |
| `customer.name` | string | `customer_basic_data.name_1` | Nama customer/perusahaan |
| `project_type` | string | `delivery_projects.project_type` | Tipe project |
| `description` | string | `delivery_projects.description` | Deskripsi project |
| `category_status` | string | `delivery_projects.category` | Status kategori: `Open`, `In Process`, `Closed` |
| `track_status` | string | `delivery_projects.status` | Status track: `On Track`, `Monitoring`, `At Risk` |
| `pic_user.id` | integer | `delivery_projects.delivery_owner_id` | ID employee PIC |
| `pic_user.name` | string | `employee_basic_data.first_name + last_name` | Nama lengkap PIC |
| `team_members` | array | `delivery_project_employee` pivot | Daftar anggota tim, PIC di index 0 |
| `progress_percent` | float | `delivery_projects.calculated_progress / 100` | Progress 0.0–1.0 |
| `start_date` | string | `delivery_projects.start_date` | Format: `YYYY-MM-DD` |
| `end_date` | string | `delivery_projects.end_date` | Format: `YYYY-MM-DD` |

---

---

## 2. Detail Project

### `GET /projects/{id}`

Mengembalikan detail lengkap satu project, termasuk phases, team members, dan updates.

### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID project |

### Response `200 OK`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "customer": {
      "id": 1,
      "name": "PT. Abadi Jaya"
    },
    "project_type": "Implementation",
    "description": "ERP full implementation phase 1 including HR and Finance modules.",
    "category_status": "In Process",
    "track_status": "On Track",
    "pic_user": {
      "id": 3,
      "name": "John Doe"
    },
    "team_members": [
      { "id": 3, "name": "John Doe" },
      { "id": 5, "name": "Jane Smith" },
      { "id": 7, "name": "Bob Wilson" }
    ],
    "progress_percent": 0.65,
    "start_date": "2026-01-01",
    "end_date": "2026-06-30",
    "phases": [
      { "id": 1, "name": "Initiation",   "progress": 1.0,  "status": "Closed"     },
      { "id": 2, "name": "Analysis",     "progress": 1.0,  "status": "Closed"     },
      { "id": 3, "name": "Development",  "progress": 0.70, "status": "In Process" },
      { "id": 4, "name": "Testing",      "progress": 0.20, "status": "In Process" },
      { "id": 5, "name": "Go-Live",      "progress": 0.0,  "status": "Open"       }
    ],
    "updates": [
      {
        "id": 1,
        "author": { "id": 3, "name": "John Doe" },
        "note": "Development phase 70% complete. API integration done.",
        "created_at": "2026-03-10"
      },
      {
        "id": 2,
        "author": { "id": 5, "name": "Jane Smith" },
        "note": "Analysis phase signed off by client. Moving to dev.",
        "created_at": "2026-02-28"
      }
    ]
  }
}
```

### Response `404 Not Found`

```json
{
  "message": "No query results for model [App\\Models\\DeliveryProject]."
}
```

### Penjelasan Field Tambahan (vs List)

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| `phases` | array | Daftar fase project, diurutkan `order_sequence` ASC |
| `phases[].id` | integer | ID phase |
| `phases[].name` | string | Nama fase (mis: `Initiation`, `Analysis`) |
| `phases[].progress` | float | Progress fase 0.0–1.0, dihitung dari rata-rata planning activities |
| `phases[].status` | string | `Open` / `In Process` / `Closed` — dihitung dari status planning activities |
| `updates` | array | Catatan update project, diurutkan terbaru dulu |
| `updates[].id` | integer | ID update |
| `updates[].author.id` | integer | employee_id pembuat update |
| `updates[].author.name` | string | Nama lengkap pembuat update |
| `updates[].note` | string | Isi catatan update |
| `updates[].created_at` | string | Tanggal update, format `YYYY-MM-DD` |

#### Logika `phases[].status`

| Kondisi planning activities | Status phase |
|-----------------------------|--------------|
| Semua berstatus `completed` | `Closed` |
| Ada yang berstatus `in_progress` atau `delayed` | `In Process` |
| Lainnya (belum ada yang dimulai) | `Open` |

---

---

## 3. Tambah Update Project

### `POST /projects/{id}/updates`

Menambahkan catatan/update baru ke project. Author otomatis dari token login.

### Path Parameter

| Parameter | Tipe | Deskripsi |
|-----------|------|-----------|
| `id` | integer | ID project |

### Request Body (JSON)

```json
{
  "note": "Development phase 70% complete. API integration done."
}
```

| Field | Tipe | Required | Deskripsi |
|-------|------|----------|-----------|
| `note` | string | **Ya** | Isi catatan update |

### Response `201 Created`

```json
{
  "success": true,
  "message": "Update berhasil ditambahkan.",
  "data": {
    "id": 4,
    "author": {
      "id": 3,
      "name": "John Doe"
    },
    "note": "Development phase 70% complete. API integration done.",
    "created_at": "2026-03-30"
  }
}
```

### Response `422 Unprocessable Entity`

```json
{
  "success": false,
  "message": "Validasi gagal.",
  "errors": {
    "note": ["The note field is required."]
  }
}
```

### Response `404 Not Found`

```json
{
  "message": "No query results for model [App\\Models\\DeliveryProject]."
}
```

---

---

## Referensi Nilai Status & Warna Badge

### `category_status` / `phases[].status`

| Nilai | Badge Background | Badge Text |
|-------|-----------------|------------|
| `Open` | `#FEF9C3` | `#CA8A04` (kuning) |
| `In Process` | `#DBEAFE` | `#2563EB` (biru) |
| `Closed` | `#DCFCE7` | `#16A34A` (hijau) |

### `track_status`

| Nilai | Badge Background | Badge Text |
|-------|-----------------|------------|
| `On Track` | `#DCFCE7` | `#16A34A` (hijau) |
| `Monitoring` | `#FEF9C3` | `#CA8A04` (kuning) |
| `At Risk` | `#FEE2E2` | `#DC2626` (merah) |

### `progress_percent` — Warna Progress Bar

| Kondisi | Warna |
|---------|-------|
| `>= 1.0` | `#16A34A` (hijau) |
| `>= 0.5` | `#2563EB` (biru) |
| `< 0.5` | `#CA8A04` (kuning) |

### `project_type` (nilai valid)

- `Implementation`
- `Customization`
- `Training`
- `Support & Maintenance`

---

---

## Catatan Teknis Backend

1. **PIC = `delivery_owner_id`** — field `pic` di model adalah teks bebas. Mobile menggunakan `deliveryOwner` (FK `delivery_owner_id`) sebagai referensi PIC terstruktur dengan `id` dan `name`.
2. **`team_members[0]` adalah PIC** — backend mengurutkan array team members dengan PIC (delivery_owner_id) selalu di posisi pertama.
3. **`phases` diurutkan** `order_sequence` ASC sesuai urutan fase proyek.
4. **`updates` diurutkan** `created_at` DESC (terbaru dulu).
5. **`progress_percent`** adalah float `0.0–1.0`. Di DB disimpan sebagai `0–100` (`calculated_progress`), dibagi 100 sebelum dikirim.
6. **`phases[].status`** tidak disimpan di DB — dihitung dari status planning activities per fase.
7. **`updates[].author`** — tabel `delivery_project_updates` tidak memiliki kolom author. Untuk update yang baru dibuat via `POST /projects/{id}/updates`, author dikembalikan dari token di response (tidak disimpan ke DB). Untuk update lama/existing, `author` bernilai `null`.
8. **Field `note`** di response API dipetakan dari kolom `notes` di tabel `delivery_project_updates`.

---

---

## Panduan Integrasi Mobile

### Alur Penggunaan API

```
1. Login
   POST /api/mobile/employee/auth/login
   → Simpan access_token & refresh_token

2. Tampilkan List Project
   GET /api/mobile/employee/projects
   → Gunakan data[] untuk render ProjectListScreen

3. Tap Project Card
   GET /api/mobile/employee/projects/{id}
   → Gunakan data untuk render:
      - Tab Overview (progress, phases)
      - Tab Team (team_members)
      - Tab Updates (updates)

4. Tambah Update (Tab Updates)
   POST /api/mobile/employee/projects/{id}/updates
   body: { "note": "..." }
   → Append item baru ke list updates
```

### Contoh Hit API — Dart / Flutter (http package)

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

const baseUrl = 'https://<domain>/api/mobile/employee';

// ─── List Project ────────────────────────────────────────────────
Future<Map<String, dynamic>> getProjects({
  String? search,
  String? status,
  int page = 1,
}) async {
  final params = {
    if (search != null && search.isNotEmpty) 'search': search,
    if (status != null && status != 'All') 'status': status,
    'page': page.toString(),
  };
  final uri = Uri.parse('$baseUrl/projects').replace(queryParameters: params);

  final response = await http.get(uri, headers: {
    'Authorization': 'Bearer $accessToken',
    'Content-Type': 'application/json',
  });

  if (response.statusCode == 200) {
    return jsonDecode(response.body);
  }
  throw Exception('Gagal memuat project: ${response.statusCode}');
}

// ─── Detail Project ───────────────────────────────────────────────
Future<Map<String, dynamic>> getProjectDetail(int id) async {
  final response = await http.get(
    Uri.parse('$baseUrl/projects/$id'),
    headers: {
      'Authorization': 'Bearer $accessToken',
      'Content-Type': 'application/json',
    },
  );

  if (response.statusCode == 200) {
    return jsonDecode(response.body)['data'];
  }
  if (response.statusCode == 404) {
    throw Exception('Project tidak ditemukan.');
  }
  throw Exception('Gagal memuat detail project: ${response.statusCode}');
}

// ─── Tambah Update ────────────────────────────────────────────────
Future<Map<String, dynamic>> addProjectUpdate(int projectId, String note) async {
  final response = await http.post(
    Uri.parse('$baseUrl/projects/$projectId/updates'),
    headers: {
      'Authorization': 'Bearer $accessToken',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({'note': note}),
  );

  if (response.statusCode == 201) {
    return jsonDecode(response.body)['data'];
  }
  if (response.statusCode == 422) {
    final errors = jsonDecode(response.body)['errors'];
    throw Exception('Validasi gagal: $errors');
  }
  throw Exception('Gagal menambah update: ${response.statusCode}');
}
```

### Contoh Hit API — JavaScript / Axios

```javascript
const BASE_URL = 'https://<domain>/api/mobile/employee';

const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
});

// Inject token ke setiap request
api.interceptors.request.use((config) => {
  const token = storage.get('access_token'); // sesuaikan storage Anda
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// ─── List Project ─────────────────────────────────────────────────
async function getProjects({ search, status, page = 1 } = {}) {
  const params = { page };
  if (search) params.search = search;
  if (status && status !== 'All') params.status = status;

  const { data } = await api.get('/projects', { params });
  return data; // { success, data[], meta }
}

// ─── Detail Project ───────────────────────────────────────────────
async function getProjectDetail(id) {
  const { data } = await api.get(`/projects/${id}`);
  return data.data; // objek detail project
}

// ─── Tambah Update ────────────────────────────────────────────────
async function addProjectUpdate(projectId, note) {
  const { data } = await api.post(`/projects/${projectId}/updates`, { note });
  return data.data; // objek update yang baru dibuat
}
```

### Best Practice Error Handling di Mobile

```dart
// Wrapper umum untuk semua API call
Future<T> safeApiCall<T>(Future<T> Function() call) async {
  try {
    return await call();
  } on SocketException {
    throw Exception('Tidak ada koneksi internet. Periksa jaringan Anda.');
  } on TimeoutException {
    throw Exception('Koneksi timeout. Coba lagi.');
  } on Exception catch (e) {
    rethrow;
  }
}

// Penanganan HTTP status code
void handleHttpError(http.Response response) {
  switch (response.statusCode) {
    case 401:
      // Token expired atau tidak valid → arahkan ke halaman login
      final body = jsonDecode(response.body);
      if (body['code'] == 'ACCESS_TOKEN_EXPIRED') {
        // Coba refresh token sebelum redirect
        refreshAndRetry();
      } else {
        navigateToLogin();
      }
      break;
    case 403:
      throw Exception('Akses ditolak.');
    case 404:
      throw Exception('Data tidak ditemukan.');
    case 422:
      final errors = jsonDecode(response.body)['errors'] as Map;
      final messages = errors.values.expand((e) => e as List).join('\n');
      throw Exception(messages);
    case 500:
      throw Exception('Terjadi kesalahan server. Coba beberapa saat lagi.');
    default:
      throw Exception('Terjadi kesalahan (${response.statusCode}).');
  }
}
```

### Strategi Refresh Token

```dart
Future<void> refreshAndRetry() async {
  try {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/refresh'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'refresh_token': storage.get('refresh_token')}),
    );
    if (response.statusCode == 200) {
      final body = jsonDecode(response.body);
      storage.set('access_token', body['data']['access_token']);
      // Ulangi request yang gagal
    } else {
      navigateToLogin(); // refresh juga expired
    }
  } catch (_) {
    navigateToLogin();
  }
}
```

---

## Arsitektur File Laravel

| File | Deskripsi |
|------|-----------|
| `app/Http/Controllers/Mobile/ProjectController.php` | Controller: `index`, `show`, `storeUpdate` |
| `app/Http/Resources/Mobile/ProjectListResource.php` | Transformer list (tanpa phases & updates) |
| `app/Http/Resources/Mobile/ProjectDetailResource.php` | Transformer detail (include phases & updates) |
| `app/Http/Resources/Mobile/ProjectPhaseResource.php` | Transformer per phase |
| `app/Http/Resources/Mobile/ProjectUpdateResource.php` | Transformer per update/catatan |
| `database/migrations/2026_03_30_000001_add_created_by_id_to_delivery_project_updates.php` | Migrasi: tambah kolom author ke tabel updates |

### Menjalankan Migrasi

```bash
php artisan migrate
```

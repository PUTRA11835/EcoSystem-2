# API Dashboard Mobile — Employee

> **Endpoint tunggal yang mengembalikan ringkasan data seluruh sistem**

---

## Daftar Isi

1. [Analisis Struktur Database](#analisis-struktur-database)
2. [Rekomendasi Dashboard](#rekomendasi-dashboard)
3. [Endpoint API](#endpoint-api)
4. [Response JSON Lengkap](#response-json-lengkap)
5. [Penjelasan Setiap Field](#penjelasan-setiap-field)
6. [Panduan Testing Postman](#panduan-testing-postman)
7. [Integrasi Flutter](#integrasi-flutter)

---

## Analisis Struktur Database

### Tabel yang Relevan untuk Dashboard

| Tabel | Fungsi |
|-------|--------|
| `ticket` | Tiket layanan pelanggan |
| `delivery_support` | Paket support/pemeliharaan per tiket |
| `delivery_projects` | Proyek implementasi/delivery |
| `timesheets` | Log jam kerja karyawan |
| `employee` | Data karyawan |
| `employee_role` | Role/jabatan karyawan |
| `customer` | Data pelanggan |
| `customer_basic_data` | Detail pelanggan (nama, group, kategori) |

---

### Field Penting per Tabel

#### `ticket`
| Field | Tipe | Nilai yang Ada |
|-------|------|----------------|
| `status` | enum | `open`, `in_progress`, `hold`, `cancel`, `closed`, `reply` |
| `ticket_priority` | enum | `Low`, `Medium`, `High` |
| `type` | string | `mo`, `ams`, `ats`, dll (bebas) |
| `man_days` | float | Estimasi hari pengerjaan |
| `employee_id` | FK | PIC / karyawan penanggung jawab |
| `customer_id` | FK | Pelanggan terkait |
| `created_at` | timestamp | Tanggal buat tiket |

#### `delivery_support`
| Field | Tipe | Keterangan |
|-------|------|------------|
| `calculated_progress` | decimal(5,2) | 0–100; **tidak ada kolom `status`** |
| `type` | string | AMS, MO, ATS, Project, Internal, dll |
| `start_date`, `end_date` | date | Periode support |
| `ticket_id` | FK unik | Tiket yang terkait |
| `client_id` | FK | Customer |

> **Status diturunkan dari `calculated_progress`:**
> - `not_started` = 0
> - `in_progress` = > 0 dan < 100
> - `completed` = >= 100

#### `delivery_projects`
| Field | Tipe | Keterangan |
|-------|------|------------|
| `status` | string | Default: `planning`; nilai lain bebas |
| `calculated_progress` | decimal(5,2) | 0–100 |
| `category` | string | Kategori proyek |
| `phase` | string | Fase saat ini |
| `start_date`, `end_date`, `go_live_estimated` | date | — |

> **Dari DashboardController yang ada:** status `completed`, `closed`, `cancel` = sudah selesai.

#### `timesheets`
| Field | Tipe | Nilai |
|-------|------|-------|
| `status` | enum | `draft`, `submitted`, `approved`, `rejected` |
| `activity_type` | enum | `development`, `meeting`, `documentation`, `testing`, `support`, `training`, `other` |
| `duration_minutes` | int | Durasi dalam menit |
| `is_billable` | boolean | Apakah bisa ditagihkan |
| `date` | date | Tanggal kerja |

---

### Relasi Antar Tabel

```
ticket ──────────────── customer (many:1)
ticket ──────────────── employee (many:1) [PIC]
ticket ──────────────── delivery_support (1:1, via ticket_id)

delivery_support ─────── customer (many:1) [client_id]
delivery_projects ────── customer (many:1) [client_id]
delivery_projects ────── employee (many:many) [team members]

timesheets ───────────── employee (many:1)
timesheets ───────────── delivery_projects (many:1)
timesheets ───────────── ticket (many:1)

employee ─────────────── employee_role (many:1)
customer ─────────────── customer_basic_data (1:1)
```

---

## Rekomendasi Dashboard

Berdasarkan data yang **benar-benar ada** di database:

### Kartu Utama (Summary Cards)
| Kartu | Sumber |
|-------|--------|
| Total Tiket | `ticket` — COUNT(*) |
| Tiket In Progress | `ticket` WHERE status = 'in_progress' |
| Total Delivery Support | `delivery_support` — COUNT(*) |
| Support In Progress | `delivery_support` WHERE progress > 0 AND < 100 |
| Total Delivery Project | `delivery_projects` — COUNT(*) |
| Project Aktif | `delivery_projects` WHERE status NOT IN (completed, closed, cancel) |
| Total Karyawan Aktif | `employee` WHERE is_active = true |
| Customer Aktif | `customer` WHERE is_active = true |

### Grafik / Chart
| Chart | Sumber |
|-------|--------|
| Distribusi status tiket (pie/bar) | `ticket` GROUP BY status |
| Tiket per prioritas | `ticket` GROUP BY ticket_priority |
| Progress rata-rata delivery | AVG(calculated_progress) |
| Jam kerja bulan ini | SUM(timesheets.duration_minutes) |
| Distribusi tipe aktivitas timesheet | `timesheets` GROUP BY activity_type |

### Data Terkini
| Data | Sumber |
|------|--------|
| Tiket baru hari ini | WHERE DATE(created_at) = TODAY |
| Tiket baru bulan ini | WHERE YEAR/MONTH created_at |
| Customer baru bulan ini | WHERE YEAR/MONTH created_at |

---

## Endpoint API

| Properti | Detail |
|----------|--------|
| Method   | `GET` |
| URL      | `/api/mobile/employee/dashboard` |
| Auth     | **Wajib** — Bearer Token (Employee) |

**Request Header:**

```
Authorization: Bearer {access_token}
```

---

## Response JSON Lengkap

```json
{
  "success": true,
  "data": {
    "ticket": {
      "total": 150,
      "by_status": {
        "open": 42,
        "in_progress": 35,
        "hold": 8,
        "cancel": 5,
        "closed": 55,
        "reply": 5
      },
      "by_priority": {
        "low": 60,
        "medium": 70,
        "high": 20
      },
      "new_today": 3,
      "new_this_month": 18,
      "by_type": [
        { "type": "ams", "count": 80 },
        { "type": "mo",  "count": 45 },
        { "type": "ats", "count": 25 }
      ]
    },
    "delivery_support": {
      "total": 48,
      "by_status": {
        "not_started": 5,
        "in_progress": 30,
        "completed": 13
      },
      "avg_progress": 62.5,
      "new_this_month": 4,
      "by_type": [
        { "type": "AMS",      "count": 25 },
        { "type": "MO",       "count": 15 },
        { "type": "Internal", "count": 8  }
      ]
    },
    "delivery_project": {
      "total": 22,
      "active": 14,
      "completed": 7,
      "cancelled": 1,
      "avg_progress": 45.8,
      "by_status": [
        { "status": "planning",    "count": 5 },
        { "status": "in_progress", "count": 9 },
        { "status": "completed",   "count": 7 },
        { "status": "cancel",      "count": 1 }
      ],
      "by_category": [
        { "category": "Implementation", "count": 12 },
        { "category": "Migration",      "count": 6  },
        { "category": "Upgrade",        "count": 4  }
      ],
      "new_this_month": 2
    },
    "timesheet": {
      "this_month": {
        "total_entries": 120,
        "total_hours": 480.5,
        "billable_hours": 350.0,
        "by_status": {
          "draft": 10,
          "submitted": 25,
          "approved": 80,
          "rejected": 5
        },
        "by_activity_type": [
          { "type": "development",   "count": 50, "hours": 200.0 },
          { "type": "meeting",       "count": 20, "hours": 40.0  },
          { "type": "testing",       "count": 15, "hours": 60.0  },
          { "type": "documentation", "count": 10, "hours": 30.0  },
          { "type": "support",       "count": 10, "hours": 80.5  },
          { "type": "training",      "count": 10, "hours": 50.0  },
          { "type": "other",         "count": 5,  "hours": 20.0  }
        ]
      }
    },
    "employee": {
      "total": 35,
      "active": 32,
      "inactive": 3,
      "by_role": [
        { "role": "Consultant",      "count": 15 },
        { "role": "Project Manager", "count": 8  },
        { "role": "Admin",           "count": 5  },
        { "role": "Helpdesk",        "count": 4  }
      ]
    },
    "customer": {
      "total": 120,
      "active": 115,
      "inactive": 5,
      "new_this_month": 3,
      "by_group": [
        { "group": "Corporate", "count": 60 },
        { "group": "SME",       "count": 40 },
        { "group": "Government","count": 15 }
      ]
    }
  },
  "generated_at": "2026-03-17T10:00:00+07:00"
}
```

---

## Penjelasan Setiap Field

### `ticket`

| Field | Tipe | Penjelasan |
|-------|------|------------|
| `total` | int | Jumlah seluruh tiket |
| `by_status.open` | int | Tiket baru, belum diproses |
| `by_status.in_progress` | int | Tiket sedang dikerjakan (**angka utama dashboard**) |
| `by_status.hold` | int | Tiket ditunda |
| `by_status.cancel` | int | Tiket dibatalkan |
| `by_status.closed` | int | Tiket selesai & ditutup |
| `by_status.reply` | int | Tiket menunggu balasan customer |
| `by_priority.low/medium/high` | int | Distribusi urgensi |
| `new_today` | int | Tiket masuk hari ini |
| `new_this_month` | int | Tiket masuk bulan ini |
| `by_type` | array | Distribusi per tipe layanan (ams, mo, ats, dll) |

### `delivery_support`

| Field | Tipe | Penjelasan |
|-------|------|------------|
| `total` | int | Jumlah seluruh delivery support |
| `by_status.not_started` | int | Belum dimulai (progress = 0%) |
| `by_status.in_progress` | int | Sedang berjalan (progress 1–99%) |
| `by_status.completed` | int | Selesai (progress >= 100%) |
| `avg_progress` | float | Rata-rata progress semua support (%) |
| `new_this_month` | int | Support baru bulan ini |
| `by_type` | array | Distribusi per tipe (AMS, MO, ATS, dll) |

### `delivery_project`

| Field | Tipe | Penjelasan |
|-------|------|------------|
| `total` | int | Jumlah seluruh proyek |
| `active` | int | Proyek yang masih berjalan |
| `completed` | int | Proyek selesai (status = completed/closed) |
| `cancelled` | int | Proyek dibatalkan |
| `avg_progress` | float | Rata-rata progress semua proyek (%) |
| `by_status` | array | Daftar semua nilai status yang ada di DB beserta jumlahnya |
| `by_category` | array | Distribusi per kategori proyek |
| `new_this_month` | int | Proyek baru bulan ini |

### `timesheet` (bulan berjalan)

| Field | Tipe | Penjelasan |
|-------|------|------------|
| `total_entries` | int | Jumlah log timesheet bulan ini |
| `total_hours` | float | Total jam kerja (menit ÷ 60) |
| `billable_hours` | float | Jam yang bisa ditagihkan ke klien |
| `by_status` | object | Draft / Submitted / Approved / Rejected |
| `by_activity_type` | array | Jam & jumlah log per tipe aktivitas |

### `employee`

| Field | Tipe | Penjelasan |
|-------|------|------------|
| `total` | int | Total karyawan (aktif + nonaktif) |
| `active` | int | Karyawan aktif |
| `inactive` | int | Karyawan nonaktif |
| `by_role` | array | Distribusi per role jabatan |

### `customer`

| Field | Tipe | Penjelasan |
|-------|------|------------|
| `total` | int | Total customer |
| `active` | int | Customer aktif |
| `inactive` | int | Customer nonaktif |
| `new_this_month` | int | Customer baru bulan ini |
| `by_group` | array | Distribusi per customer group |

---

## Panduan Testing Postman

### Langkah 1 — Pastikan Sudah Login

Ikuti panduan di `API_MOBILE_EMPLOYEE_AUTH.md` untuk login dan simpan `employee_access_token`.

---

### Langkah 2 — Hit Endpoint Dashboard

1. Buat request baru: `GET {{base_url}}/mobile/employee/dashboard`
2. Tab **Auth** → `Bearer Token` → `{{employee_access_token}}`
3. Klik **Send**
4. Response `200` berisi seluruh data ringkasan

---

### Langkah 3 — Verifikasi Data

Pastikan response mengandung:
- ✅ `data.ticket.total` > 0 (jika ada tiket)
- ✅ `data.ticket.by_status.in_progress` = jumlah tiket aktif
- ✅ `data.delivery_support.total` = jumlah delivery support
- ✅ `data.delivery_project.total` = jumlah proyek
- ✅ `generated_at` = waktu saat ini

---

### Test Error: Token Expired

Gunakan access token yang sudah expired → pastikan response:
```json
{
  "success": false,
  "message": "Access token sudah expired. Gunakan refresh token untuk memperbarui.",
  "code": "ACCESS_TOKEN_EXPIRED"
}
```

---

## Integrasi Flutter

### Model Data Dashboard

```dart
class DashboardData {
  final TicketSummary ticket;
  final DeliverySummary deliverySupport;
  final ProjectSummary deliveryProject;
  final TimesheetSummary timesheet;
  final EmployeeSummary employee;
  final CustomerSummary customer;

  DashboardData.fromJson(Map<String, dynamic> json)
      : ticket          = TicketSummary.fromJson(json['ticket']),
        deliverySupport = DeliverySummary.fromJson(json['delivery_support']),
        deliveryProject = ProjectSummary.fromJson(json['delivery_project']),
        timesheet       = TimesheetSummary.fromJson(json['timesheet']),
        employee        = EmployeeSummary.fromJson(json['employee']),
        customer        = CustomerSummary.fromJson(json['customer']);
}

class TicketSummary {
  final int total;
  final int open;
  final int inProgress;
  final int hold;
  final int cancel;
  final int closed;
  final int reply;
  final int priorityLow;
  final int priorityMedium;
  final int priorityHigh;
  final int newToday;
  final int newThisMonth;

  TicketSummary.fromJson(Map<String, dynamic> json)
      : total          = json['total'],
        open           = json['by_status']['open'],
        inProgress     = json['by_status']['in_progress'],
        hold           = json['by_status']['hold'],
        cancel         = json['by_status']['cancel'],
        closed         = json['by_status']['closed'],
        reply          = json['by_status']['reply'],
        priorityLow    = json['by_priority']['low'],
        priorityMedium = json['by_priority']['medium'],
        priorityHigh   = json['by_priority']['high'],
        newToday       = json['new_today'],
        newThisMonth   = json['new_this_month'];
}

class DeliverySummary {
  final int total;
  final int notStarted;
  final int inProgress;
  final int completed;
  final double avgProgress;
  final int newThisMonth;

  DeliverySummary.fromJson(Map<String, dynamic> json)
      : total        = json['total'],
        notStarted   = json['by_status']['not_started'],
        inProgress   = json['by_status']['in_progress'],
        completed    = json['by_status']['completed'],
        avgProgress  = (json['avg_progress'] as num).toDouble(),
        newThisMonth = json['new_this_month'];
}

class ProjectSummary {
  final int total;
  final int active;
  final int completed;
  final int cancelled;
  final double avgProgress;
  final int newThisMonth;

  ProjectSummary.fromJson(Map<String, dynamic> json)
      : total        = json['total'],
        active       = json['active'],
        completed    = json['completed'],
        cancelled    = json['cancelled'],
        avgProgress  = (json['avg_progress'] as num).toDouble(),
        newThisMonth = json['new_this_month'];
}

class TimesheetSummary {
  final int totalEntries;
  final double totalHours;
  final double billableHours;
  final int approved;
  final int submitted;

  TimesheetSummary.fromJson(Map<String, dynamic> json) {
    final m = json['this_month'];
    totalEntries  = m['total_entries'];
    totalHours    = (m['total_hours'] as num).toDouble();
    billableHours = (m['billable_hours'] as num).toDouble();
    approved      = m['by_status']['approved'];
    submitted     = m['by_status']['submitted'];
  }
}

class EmployeeSummary {
  final int total;
  final int active;
  EmployeeSummary.fromJson(Map<String, dynamic> json)
      : total  = json['total'],
        active = json['active'];
}

class CustomerSummary {
  final int total;
  final int active;
  final int newThisMonth;
  CustomerSummary.fromJson(Map<String, dynamic> json)
      : total        = json['total'],
        active       = json['active'],
        newThisMonth = json['new_this_month'];
}
```

---

### Service: Ambil Data Dashboard

```dart
class DashboardService {
  final EmployeeApiClient _api = EmployeeApiClient();

  Future<DashboardData> getDashboard() async {
    try {
      final response = await _api.dio.get('/dashboard');
      return DashboardData.fromJson(response.data['data']);
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Gagal memuat dashboard.');
    }
  }
}
```

---

### Contoh Widget Dashboard

```dart
class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});
  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  final _service = DashboardService();
  DashboardData? _data;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    try {
      final data = await _service.getDashboard();
      setState(() { _data = data; _loading = false; });
    } catch (e) {
      setState(() { _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_data == null) return const Center(child: Text('Gagal memuat data.'));

    return RefreshIndicator(
      onRefresh: _loadDashboard,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ─── Baris 1: Tiket ───
            Text('Tiket', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Row(children: [
              _SummaryCard(label: 'Total',       value: _data!.ticket.total,      color: Colors.blue),
              _SummaryCard(label: 'In Progress', value: _data!.ticket.inProgress, color: Colors.orange),
              _SummaryCard(label: 'Open',        value: _data!.ticket.open,       color: Colors.green),
              _SummaryCard(label: 'High',        value: _data!.ticket.priorityHigh, color: Colors.red),
            ]),

            const SizedBox(height: 16),

            // ─── Baris 2: Delivery ───
            Text('Delivery', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Row(children: [
              _SummaryCard(label: 'Support',     value: _data!.deliverySupport.total,   color: Colors.purple),
              _SummaryCard(label: 'S. Progress', value: _data!.deliverySupport.inProgress, color: Colors.teal),
              _SummaryCard(label: 'Project',     value: _data!.deliveryProject.total,   color: Colors.indigo),
              _SummaryCard(label: 'P. Aktif',    value: _data!.deliveryProject.active,  color: Colors.cyan),
            ]),

            const SizedBox(height: 16),

            // ─── Baris 3: Timesheet & SDM ───
            Text('Bulan Ini', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Row(children: [
              _SummaryCard(label: 'Jam Kerja',  value: _data!.timesheet.totalHours.toInt(), color: Colors.brown),
              _SummaryCard(label: 'Karyawan',   value: _data!.employee.active,  color: Colors.blueGrey),
              _SummaryCard(label: 'Customer',   value: _data!.customer.active,  color: Colors.pink),
              _SummaryCard(label: 'Cust. Baru', value: _data!.customer.newThisMonth, color: Colors.amber),
            ]),
          ],
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final String label;
  final int value;
  final Color color;
  const _SummaryCard({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Card(
        color: color.withOpacity(0.1),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          child: Column(
            children: [
              Text('$value', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: color)),
              const SizedBox(height: 4),
              Text(label, style: const TextStyle(fontSize: 11), textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
    );
  }
}
```

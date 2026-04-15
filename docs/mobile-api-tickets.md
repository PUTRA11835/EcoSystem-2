# Mobile API — Ticket List

Base URL: `/api/mobile/employee`

Semua endpoint di bawah ini memerlukan **Bearer token** dari hasil login employee
(`Authorization: Bearer <token>`).

---

## GET `/api/mobile/employee/tickets`

Menampilkan daftar tiket dengan data lengkap sesuai tampilan web:
Last Update, Nomor Tiket, Date, Customer, PIC, Priority, Scale, Status, Jarvies Status, Type.

### Query Parametersphp

| Parameter        | Tipe    | Wajib | Keterangan |
|------------------|---------|-------|------------|
| `search`         | string  | Tidak | Cari berdasarkan description atau nama customer |
| `status`         | string  | Tidak | `Open` \| `In Progress` \| `Hold` \| `Reply` \| `Closed` |
| `assigned_to_me` | boolean | Tidak | `true` → hanya tiket yang di-assign ke user login |
| `page`           | integer | Tidak | Nomor halaman (default: `1`, per halaman: `15`) |

### Response

```json
{
  "success": true,
  "stats": {
    "total": 120,
    "in_progress": 30,
    "hold": 10,
    "closed": 50
  },
  "data": [
    {
      "id": 1,
      "ticket_number": "TKT-ABC12345-20260101",
      "description": "Error saat export laporan PDF",

      "date": "2026-01-15 08:30:00",
      "last_update": "2026-04-10 14:22:00",

      "customer": {
        "id": 5,
        "name": "PT. Contoh Jaya"
      },

      "pic": {
        "id": 12,
        "name": "Budi Santoso"
      },

      "priority": "High",

      "scale": 2.5,

      "status": "In Progress",
      "status_raw": "in_progress",

      "jarvies_status": "in process",

      "type": "Incident"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 8,
    "total": 120
  }
}
```

### Penjelasan Field

| Field           | Tipe           | Keterangan |
|-----------------|----------------|------------|
| `id`            | integer        | Primary key tiket |
| `ticket_number` | string \| null | Nomor tiket (contoh: `TKT-ABC12345-20260101`) |
| `description`   | string \| null | Isi/deskripsi tiket |
| `date`          | datetime       | Tanggal tiket dibuat (`created_at`) |
| `last_update`   | datetime       | Waktu aktivitas terakhir — `last_message_at` jika ada, fallback `updated_at` |
| `customer.id`   | integer \| null | ID customer |
| `customer.name` | string \| null | Nama customer |
| `pic.id`        | integer \| null | ID employee PIC |
| `pic.name`      | string \| null | Nama lengkap PIC (`null` = Unassigned) |
| `priority`      | string \| null | `Low` \| `Medium` \| `High` \| `Very High` |
| `scale`         | float \| null  | Man-days pengerjaan (satuan hari) |
| `status`        | string         | Label status: `Open` \| `In Progress` \| `Hold` \| `Reply` \| `Closed` \| `Cancelled` |
| `status_raw`    | string         | Nilai DB mentah: `open` \| `in_progress` \| `hold` \| `reply` \| `closed` \| `cancel` |
| `jarvies_status`| string \| null | Status dari Jarvies — lihat nilai di bawah |
| `type`          | string \| null | Tipe tiket — lihat nilai di bawah |

### Nilai `jarvies_status`

| Nilai DB               | Label di UI          |
|------------------------|----------------------|
| `sent it to support`   | To Support           |
| `in process`           | In Process           |
| `author action`        | Author Action        |
| `proposed solution`    | Proposed Solution    |
| `sent in to SAP`       | Sent to SAP          |
| `closed`               | Closed               |

### Nilai `type`

| Nilai                | Keterangan |
|----------------------|------------|
| `Incident`           | Insiden / bug produksi |
| `Service Request`    | Permintaan layanan |
| `Change Request`     | Permintaan perubahan |
| `Consult`            | Konsultasi |

---

## GET `/api/mobile/employee/tickets/stats`

Statistik jumlah tiket per status (untuk stats row / summary bar).

### Response

```json
{
  "success": true,
  "data": {
    "total": 120,
    "open": 20,
    "in_progress": 30,
    "hold": 10,
    "reply": 10,
    "closed": 50
  }
}
```

---

## GET `/api/mobile/employee/tickets/{id}`

Detail satu tiket.

### Response

```json
{
  "success": true,
  "data": { ...TicketDetail... }
}
```

---

## Contoh Request (Flutter / Dart)

```dart
final response = await http.get(
  Uri.parse('$baseUrl/api/mobile/employee/tickets').replace(queryParameters: {
    'page': '1',
    'status': 'In Progress',
    'assigned_to_me': 'false',
  }),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);
```

---

## Notes untuk Mobile Developer

- **Pagination**: gunakan `meta.current_page` dan `meta.last_page` untuk infinite scroll.
- **last_update vs date**: gunakan `last_update` untuk sorting / tampilan "waktu terakhir aktif", dan `date` untuk menampilkan kapan tiket pertama kali dibuat.
- **scale**: nilai float, satuan man-days. Tampilkan sebagai `2.5 days` atau `—` jika `null`.
- **pic.name = null**: berarti tiket belum di-assign. Tampilkan `Unassigned`.
- **status_raw**: gunakan untuk logika filter di sisi client (misal warna badge). `status` untuk teks display.

# Reporting

## Ringkasan

Modul reporting menyediakan dua jenis laporan terkait timesheet dukungan (support):

1. **Timesheet Report** — Detail timesheet per baris dengan perbandingan quota vs realisasi mandays
2. **MD Recap** — Rekap mandays per employee dikelompokkan berdasarkan mode kerja (OnSite/Remote)

Keduanya mendukung ekspor ke format Excel (.xlsx).

---

## Akses

| Role | Timesheet Report | MD Recap | Export |
|---|---|---|---|
| Admin (1) | ✓ semua employee | ✓ semua employee | ✓ |
| Head of Support (5) | ✓ semua employee | ✓ semua employee | ✓ |
| Lainnya | ✗ (redirect/403) | ✗ (403) | ✗ |

---

## Aturan Periode (21-20)

Semua data difilter berdasarkan periode. Konvensi yang digunakan:

```
Periode bulan M = Tanggal 21 bulan (M-1) sampai 20 bulan M

Contoh:
  Periode April 2026 = 21 Maret 2026 – 20 April 2026
  Periode Januari 2026 = 21 Desember 2025 – 20 Januari 2026
```

---

## 1. Timesheet Report

**URL:** `/reporting`
**Controller:** `ReportingController::index()` dan `timesheetSupport()`

### Data yang Ditampilkan

Setiap baris mewakili satu timesheet dengan status `submitted` atau `approved` yang memiliki `ticket_id`.

| Kolom | Sumber |
|---|---|
| Employee | `employee_basic_data.first_name + last_name` |
| Tanggal | `timesheets.date` |
| Ticket | `ticket.ticket_number` |
| Customer | `customer_basic_data.name_1` |
| Realisasi MD | `timesheets.md_consumed` |
| Quota MD | `consultant_mandays_detail.mandays + approved_additional` |
| Kumulatif MD | Running total per (employee + ticket) diurutkan dari tanggal terlama |
| Sisa MD | `quota - kumulatif` |
| MD Status | `Match` / `Less` / `Over` |
| Status Timesheet | `Submitted` / `Approved` |

### Logika Quota MD

```
Quota = consultant_mandays_detail.mandays
      + consultant_mandays_detail.approved_additional

Sumber: ConsultantMandays terbaru per ticket dengan status 'approved'
        → ambil ConsultantMandaysDetail per employee
```

### Logika Kumulatif & Status

Data diurutkan ascending berdasarkan tanggal untuk menghitung running total yang benar:

```
kumulatif[employee+ticket] += md_consumed  (per baris, urutan tanggal ASC)

sisa = quota - kumulatif

md_status:
  null   → quota tidak ada (tidak ada proposal mandays disetujui)
  Match  → kumulatif == quota
  Less   → kumulatif < quota
  Over   → kumulatif > quota
```

Tampilan di tabel diurutkan **terbaru di atas** (DESC) setelah kumulatif selesai dihitung.

### Summary Cards

- **Total Entries** — jumlah baris data
- **Match** — jumlah baris dengan status Match (hijau)
- **Less** — jumlah baris dengan status Less (kuning)
- **Over** — jumlah baris dengan status Over (merah)

### Filter

| Filter | Tipe | Keterangan |
|---|---|---|
| Period | Bulan + Tahun | Dikonversi ke rentang tanggal menggunakan aturan 21-20 |
| Employee | Text search | Client-side, substring match case-insensitive |
| Status | Dropdown | All / Match / Less / Over |

### Join Tabel

```sql
timesheets
  JOIN employee ON timesheets.employee_id = employee.employee_id
  JOIN employee_basic_data ON employee.employee_id = employee_basic_data.employee_id
  JOIN ticket ON timesheets.ticket_id = ticket.ticket_id
  LEFT JOIN customer ON ticket.customer_id = customer.customer_id
  LEFT JOIN customer_basic_data ON customer.customer_id = customer_basic_data.customer_id

WHERE timesheets.status IN ('submitted', 'approved')
  AND timesheets.ticket_id IS NOT NULL
  AND timesheets.deleted_at IS NULL
  AND timesheets.date BETWEEN {start} AND {end}
```

---

## 2. MD Recap

**URL:** `/reporting/md-recap`
**Controller:** `ReportingController::mdRecapIndex()` dan `mdRecap()`

### Data yang Ditampilkan

Setiap baris mewakili satu timesheet dengan status `approved` yang memiliki nilai `presence`.

| Kolom | Sumber |
|---|---|
| Employee | `employee_basic_data.first_name + last_name` |
| Tanggal | `timesheets.date` |
| Mode | `OnSite` atau `Remote` (dari `timesheets.presence`) |
| Mandays | Lihat logika di bawah |

### Logika Penghitungan Mandays

```sql
COALESCE(
    timesheets.md_consumed,              -- Primary: nilai MD eksplisit
    timesheets.duration_minutes / 480.0, -- Fallback: menit → mandays (480 menit = 1 hari)
    0                                    -- Default: nol
)
```

### Logika Mode (OnSite / Remote)

```sql
CASE WHEN LOWER(timesheets.presence) = 'onsite' THEN 'OnSite'
     ELSE 'Remote'
END
```

### Summary Stats

- **Total MD** — jumlah seluruh mandays
- **Jumlah Employee** — unique employee yang muncul
- **OnSite MD** — subtotal mandays mode OnSite
- **Remote MD** — subtotal mandays mode Remote

### Tampilan Tabel

Data dikelompokkan per employee:
- **Header row employee** — nama, avatar inisial
- **Sub-row per timesheet** — bullet, tanggal, badge mode, nilai mandays

### Filter

| Filter | Tipe | Keterangan |
|---|---|---|
| Period | Bulan + Tahun | Rentang tanggal 21-20 |
| Employee | Text search | Client-side |
| Mode | Dropdown | All / OnSite / Remote |

### Query

```sql
SELECT
    timesheets.id,
    timesheets.date,
    TRIM(CONCAT(first_name, ' ', last_name)) as employee_name,
    CASE WHEN LOWER(presence) = 'onsite' THEN 'OnSite' ELSE 'Remote' END as mode,
    COALESCE(md_consumed, duration_minutes / 480.0, 0) as mandays
FROM timesheets
JOIN employee ON timesheets.employee_id = employee.employee_id
JOIN employee_basic_data ON employee.employee_id = employee_basic_data.employee_id
WHERE timesheets.status = 'approved'
  AND timesheets.deleted_at IS NULL
  AND timesheets.date BETWEEN {start} AND {end}
  AND timesheets.presence IS NOT NULL
ORDER BY employee_name, date
```

---

## Ekspor Excel

### Timesheet Report Export

**URL:** `GET /reporting/export-excel`
**Filename:** `Timesheet_Report_{Month}_{Year}.xlsx`

| Kolom | Sumber |
|---|---|
| Tiket | `ticket_number` |
| Nama | `employee_name` |
| Bulan | `period_month` |
| Tahun | `period_year` |
| Quota Mandays | `jatah_md` |
| Realisasi MD | `md_consumed` |
| Status | Match / Less / Over |

**Styling:**
- Header: teks putih, background merah tua (`#CC0000`)
- Baris data: alternating hijau muda (`#E2EFDA`) dan putih
- Kolom Status berwarna sesuai nilai:
  - Match → Hijau (`#92D050`)
  - Less → Kuning (`#FFFF00`)
  - Over → Merah (`#FF0000`)

### MD Recap Export

**URL:** `GET /reporting/md-recap/export`
**Filename:** `MD_Recap_{Month}_{Year}.xlsx`

| Kolom | Sumber |
|---|---|
| Name | `employee_name` |
| Date | `date` |
| Mode | `OnSite` / `Remote` |
| Mandays | `mandays` |

**Styling:**
- Header: teks putih, background merah tua
- Baris: alternating hijau muda dan putih
- Kolom Mandays: center-aligned

---

## API Endpoints

| Method | URL | Fungsi |
|---|---|---|
| `GET` | `/reporting` | Halaman Timesheet Report (web) |
| `GET` | `/reporting/export-excel` | Download Excel Timesheet Report |
| `GET` | `/reporting/md-recap` | Halaman MD Recap (web) |
| `GET` | `/reporting/md-recap/export` | Download Excel MD Recap |
| `GET` | `/api/reporting/timesheet-support` | JSON data Timesheet Report |
| `GET` | `/api/reporting/md-recap` | JSON data MD Recap |
| `GET` | `/api/reporting/current-period` | Info periode yang sedang aktif |
| `POST` | `/api/reporting/close-period` | Tutup periode global (RPMO) |

### Response: `GET /api/reporting/current-period`

```json
{
  "success": true,
  "data": {
    "year": 2026,
    "month": 4,
    "is_closed": false,
    "closed_at": null,
    "start_date": "2026-03-21",
    "end_date": "2026-04-20"
  }
}
```

---

## File Terkait

| File | Peran |
|---|---|
| `app/Http/Controllers/ReportingController.php` | Controller utama |
| `app/Exports/TimesheetReportExport.php` | Export Excel Timesheet Report |
| `app/Exports/MdRecapExport.php` | Export Excel MD Recap |
| `app/Models/ReportingPeriod.php` | Model periode + helper `periodFor()`, `dateRange()` |
| `app/Models/ConsultantMandays.php` | Proposal mandays |
| `app/Models/ConsultantMandaysDetail.php` | Detail quota per employee |
| `routes/web.php` | Route halaman dan ekspor |
| `routes/api.php` | Route API reporting |
| `resources/views/reporting/reporting.blade.php` | Halaman Timesheet Report |
| `resources/views/reporting/md-recap.blade.php` | Halaman MD Recap |

# Master Data: Grade & Home Base

## Ringkasan

Grade dan Home Base employee dikelola sebagai **single source of truth** — daftar opsinya
tidak lagi di-hardcode di blade. Tujuannya agar konsisten di semua form (master employee,
profile, import CSV) dan **siap dikaitkan dengan harga Man-Day (MD)** tanpa perlu membuat
struktur baru.

Dokumen ini mencatat apa yang **sudah disiapkan** dan **apa yang masih menunggu** (mis. wiring
harga MD), supaya pekerjaan lanjutan tidak membuat ulang yang sudah ada atau meninggalkan
setelan yang tak terpakai.

> Tanggal disiapkan: 11 Jun 2026. Lihat juga memory `employee-homebase-grade-jun2026`.
>
> **Update 17 Jul 2026:** Grade **tidak lagi dipakai di Employee Basic Data**. Kolom
> `employee_basic_data.grade` sudah di-drop (91 nilai lama sengaja tidak dimigrasikan —
> keputusan pemilik produk). Konsepnya pindah jadi field **"Level"** di Employee
> Qualification (khusus tipe *Certification*) — lihat `qualification_level` di tabel
> `employee_qualification`. Tabel `grades` & `App\Models\Grade` TETAP ada sebagai
> single source of truth, dengan method baru `Grade::levelOptions()` yang men-strip
> suffix " Consultant" (mis. "Junior Consultant" → "Junior") untuk dropdown Level itu.
> Bagian di bawah yang menyebut Grade di Basic Data sudah usang, dibiarkan sebagai
> arsip histori keputusan awal.

---

## Keputusan Desain (penting — jangan diubah tanpa alasan)

| Aspek | Keputusan | Alasan |
|---|---|---|
| Grade | **Tabel referensi** `grades` | Grade membawa data harga MD yang berubah runtime & perlu di-query/join. Bukan enum PHP (harga ≠ konstanta kode) dan bukan kolom `ENUM` MySQL (menolak nilai import di luar daftar, susah feed ke view, tiap tambah nilai perlu migration, berisiko di DB bersama Jarvies). |
| `employee_basic_data.grade` | **Tetap string nama** (bukan FK `grade_id`) | Minim disrupsi: data lama aman, import tetap berbasis nama. Penghubung ke `grades` lewat `name`. FK `grade_id` boleh ditambah belakangan bila perlu integritas referensial. |
| Home Base | **Enum PHP** `App\Enums\HomeBase` | Label murni (3 lokasi kantor), tidak membawa data harga. Cukup enum. **Jika kelak tarif bergantung lokasi**, naikkan ke tabel referensi seperti Grade. |

---

## Database Schema

### Tabel: `grades`

Migration: `database/migrations/2026_06_11_000002_create_grades_table.php` (sudah di-seed 7 grade).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `name` | string(100) unique | Nama grade — penghubung ke `employee_basic_data.grade` |
| `md_price` | decimal(15,2) nullable | **Harga Man-Day per grade. Masih NULL — diisi saat fitur harga MD aktif.** |
| `sort_order` | int | Urutan tampil di dropdown |
| `is_active` | bool | Nonaktifkan grade tanpa hapus (dropdown & importer hanya baca yang aktif) |
| `timestamps` | | |

**Seed awal (sort_order 1–7):** Management Trainee, Junior Consultant, Associate Consultant,
Middle Consultant, Senior Consultant, Principal Consultant, Expert Consultant.

### Home Base — `App\Enums\HomeBase`

Bukan tabel. Nilai: `Jakarta`, `Yogyakarta`, `Surabaya`, **`Others`**.

`Others` = penanda employee **External** (lihat bagian berikut).

### Employee Type (Internal / External) — `employee_basic_data.employee_type`

Kolom `string(20)` (migration `2026_06_11_000003_add_employee_type_to_employee_basic_data`).
**Aturan tunggal:** `home_base == "Others"` → **External**, selain itu **Internal**.
Disimpan (denormalisasi) untuk query/filter & **harga MD** (tarif internal vs external beda).

- Sumber kebenaran tunggal: **`App\Models\EmployeeBasicData::deriveEmployeeType(?string $homeBase): string`**.
  JANGAN duplikasi aturan ini — selalu panggil helper.
- Disetel otomatis (server-side) di **importer**, **EmployeeController** (store/update), dan
  **EmployeeBasicDataController** (store/update) setiap kali home_base disimpan. Tidak ada
  input manual `employee_type` di form — ia murni turunan dari Home Base.
- Migration mem-backfill data lama dari `home_base`.
- UI: badge **External** (amber) di list employee + badge tipe di header detail.

---

## Sumber Daftar Opsi (single source of truth)

```
Grade     → App\Models\Grade::options()   (where is_active, order by sort_order → array nama)
Home Base → App\Enums\HomeBase::options() (['Jakarta','Yogyakarta','Surabaya'])
```

Keduanya di-inject ke view lewat **View Composer** di `AppServiceProvider::boot()`:

```php
View::composer(
    ['master.employee.index', 'master.employee.sections.basicdata'],
    fn ($view) => $view->with('gradeOptions', Schema::hasTable('grades') ? Grade::options() : [])
                       ->with('homeBaseOptions', HomeBase::options())
);
```

Blade me-render dropdown via `@foreach($gradeOptions ...)` / `@foreach($homeBaseOptions ...)`.
**Jangan hardcode ulang opsi di blade.** Jika ada form/view baru yang butuh dropdown ini,
tambahkan nama view-nya ke daftar target View Composer di atas.

---

## Alur per Komponen

| Komponen | File | Catatan |
|---|---|---|
| Daftar opsi | `app/Models/Grade.php`, `app/Enums/HomeBase.php` | `options()` |
| Inject ke view | `app/Providers/AppServiceProvider.php` | View Composer |
| Form create/edit | `resources/views/master/employee/index.blade.php` | `@foreach` dropdown |
| Form detail/profile | `resources/views/master/employee/sections/basicdata.blade.php` | `@foreach` dropdown |
| Import CSV | `app/Http/Controllers/AdminBackupController.php` → `importEmployees()` | Normalisasi case-insensitive ke nilai kanonik; tak dikenali → simpan apa adanya + warning non-fatal |
| Tampilan toleran | `public/js/custom-dropdown.js` → `setCustomDropdownValue()` | Nilai di luar daftar tetap tampil apa adanya (tidak jatuh ke placeholder) |

---

## TODO / Pekerjaan Lanjutan (yang sudah disiapkan tempatnya)

1. **Isi harga MD per grade.** Kolom `grades.md_price` sudah ada (masih NULL).
   - Perlu: UI admin CRUD tabel `grades` (CRUD nama + isi `md_price`), atau seeder/manual update.
2. **Wiring harga MD ke cost/revenue plan.** `DeliveryProjectCostController` belum membaca grade.
   - Saat diimplementasi: `join grades` by nama (atau via `grade_id` bila FK ditambah). Skema sudah siap, **tidak perlu migration baru**.
3. **(Opsional) FK `grade_id`** di `employee_basic_data` bila butuh integritas referensial —
   migrasi data string nama → id.
4. **(Opsional) Home Base jadi tabel** bila tarif ternyata bergantung lokasi.

---

## Yang HARUS dihindari (agar tidak duplikat / setelan mubazir)

- ❌ Jangan buat tabel/enum baru untuk grade — pakai `grades` + `Grade::options()`.
- ❌ Jangan hardcode opsi grade/home base di blade baru — pakai `$gradeOptions` / `$homeBaseOptions`.
- ❌ Jangan ubah `grades.name` seed yang sudah ada (akan memutus link dengan `employee_basic_data.grade` string lama).
- ❌ Jangan tambah migration untuk harga MD — kolom `md_price` sudah tersedia.
- ❌ Jangan tulis logika internal/external sendiri — pakai `EmployeeBasicData::deriveEmployeeType()`.
- ❌ Jangan jadikan `employee_type` field input manual — ia turunan dari Home Base (`Others` → External).

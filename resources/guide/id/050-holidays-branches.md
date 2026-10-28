---
key: holidays-branches
chapter: administration
order: 50
title: Holidays & Branches
summary: Kelola kalender hari libur perusahaan dan daftar cabang, termasuk lokasi presensi tiap cabang.
requires: [management.holidays, management.branches]
updated: 2026-10-27
---
Holidays dan Branches adalah data induk seluruh perusahaan. Holidays dipakai oleh kalender dan pemilih tanggal. Branches dipakai oleh beberapa menu, seperti Attendance, Reimbursement, Purchase Request, dan Cash Advance. Topik ini ditujukan untuk HR dan administrator.

## Siapa yang menggunakan

HR dan administrator. Halamannya adalah `Management` › `Holidays` dan `Management` › `Branches`. Tombol tambah, ubah, dan hapus hanya tampil bila Anda memiliki akses yang sesuai. Tanpa akses itu, Anda tetap dapat membaca daftarnya.

## Sebelum Anda mulai

- Untuk hari libur, siapkan daftar resmi libur nasional dan cuti bersama tahun tersebut.
- Untuk cabang, ketahui kode, alamat, dan, bila presensi diperiksa berdasarkan lokasi, koordinat lokasinya.

## Langkah demi langkah

### Menambah hari libur

1. Buka `Management` › `Holidays`.
2. Bila perlu, saring daftar berdasarkan tahun melalui `All Years`.
3. Klik `Add Holiday`.
4. Isi `Name` dan `Date`, lalu pilih `Type`: `National Holiday`, `Collective Leave`, atau `Custom Holiday`.
5. Biarkan `Active (applied to calendar & date pickers)` tercentang agar hari tersebut dipakai.
6. Klik `Save`.

Untuk mengubah atau menghapus hari libur, gunakan ikon aksi pada barisnya lalu konfirmasi. Hilangkan centang `Active` untuk berhenti memakai hari libur tanpa menghapusnya.

> [!IMPORTANT]
> Hari libur berlaku untuk semua Employee, sehingga perubahan memengaruhi kalender dan pemilih tanggal seluruh perusahaan. Periksa tanggal dan tipenya sebelum menyimpan.

### Menambah cabang

1. Buka `Management` › `Branches`.
2. Gunakan `Search` dan `Status` (`All`, `Active`, `Inactive`) untuk melihat cabang yang ada, agar tidak membuat duplikat.
3. Klik `Add Branch`.
4. Isi `Branch Code` (harus unik), `Branch Name`, alamat, kota, provinsi, dan telepon.
5. Untuk lokasi presensi, pilih titik di `Choose Location on Map` (klik peta atau geser pin) atau isi `Latitude` dan `Longitude`. Atur `Geofence Radius (meters)`, yaitu jarak dari titik pusat yang masih dianggap di lokasi.
6. Pilih `Geofence Policy`: ikuti pengaturan perusahaan, `Off` (lokasi tidak diperiksa), `Flag` (dicatat dan ditandai bila di luar radius), atau `Enforce` (ditolak bila di luar radius).
7. Centang `Head Office` bila ini kantor pusat. Hanya satu cabang yang boleh menjadi kantor pusat; memilihnya menghapus tanda dari cabang lain.
8. Atur `Status`. Cabang `Inactive` dikecualikan dari validasi presensi.
9. Simpan cabang.

### Mengubah atau menghapus cabang

Gunakan ikon ubah pada baris untuk mengubah cabang. Ikon hapus menghilangkannya dari daftar setelah konfirmasi. Catatan presensi yang sudah tersimpan tetap dipertahankan.

## Apa yang terjadi selanjutnya

Hari libur muncul di kalender semua orang. Cabang tersedia di menu yang memakainya, dan pengaturan geofence berlaku pada Check-in presensi.

## Pertanyaan umum

**Bolehkah dua cabang memiliki kode yang sama?** Tidak, kode harus unik.

**Seorang Employee ditandai di luar area.** Periksa koordinat dan radius cabang, serta `Geofence Policy`. Lihat [[attendance-management]].

## Topik terkait

- [[attendance-management]]
- [[my-attendance]]
- [[roles-and-menu-access]]

<!-- sources: routes/web.php, app/Http/Controllers/HolidayManagementController.php, app/Http/Controllers/Management/BranchController.php, resources/views/management/holidays/index.blade.php, resources/views/management/branches/index.blade.php, resources/views/management/branches/form.blade.php -->

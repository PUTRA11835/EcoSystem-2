# User Guide: siapa melihat topik apa (tambahan untuk branch ini)

Berkas berawalan `_` tidak dimuat sebagai topik. Topik tampil menurut **hak akses pengguna yang login**, lewat `requires` di front matter. Ada dua jenis syarat, dan cukup salah satu yang dimiliki pengguna (tanpa `requires` berarti semua pengguna).

## 1. Izin menu (untuk fitur)
`requires: [rpmo.periods]` berarti topik tampil untuk setiap role yang saat ini boleh membuka menu itu di Menu Access. Ini dipakai untuk topik fitur, misalnya `period-management`, karena otomatis ikut berubah saat administrator mengubah akses.

## 2. Role (untuk panduan satu role)
`requires: ["@role:Delivery RPMO Head"]` berarti topik tampil hanya untuk karyawan yang memegang role bernama persis itu. Dipakai untuk panduan per role (`guide-ds-user`, `guide-ds-head`, `guide-ds-manager`, `guide-rpmo-head`). Nama role harus sama dengan kolom `name` di tabel `employee_role`; `guide-check.php` menolak nama yang tidak ada.

- Karyawan dengan **beberapa role** melihat gabungan topik semua rolenya.
- Beberapa syarat boleh digabung dalam satu daftar, misalnya `requires: ["@role:Delivery Support Manager", "@role:Delivery Support Manager II"]`.

## Tautan antartopik
Tautan `[[key]]` hanya boleh menuju topik yang **pasti terlihat oleh setiap pembaca topik asalnya**. Mesin membuat tautan ke topik yang disembunyikan menjadi tautan mati yang menampilkan judul topik itu. Cek dengan menjalankan matriks per role (lihat catatan proyek) setelah menambah tautan.

## Yang menjaga aturan ini
- `php docs/HRGA/tools/guide-check.php`: slug menu dan nama role harus ada, kedua bahasa berpasangan.
- `tests/Unit/Guide/UserGuideRoleTest.php`: izin semu role membuka topik yang tepat, gabungan dua role, dan `requires` sama di EN dan ID.
- `UserGuideController` menghitung izin semu `@role:` dari semua penugasan role karyawan yang login.

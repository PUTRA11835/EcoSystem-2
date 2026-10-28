---
key: roles-and-menu-access
chapter: administration
order: 10
title: Role & Menu Access
summary: Buat Role, tentukan menu yang dapat dilihat tiap Role dan apa yang boleh dilakukan, serta tinjau perubahan akses.
requires: [management.roles, management.permissions]
updated: 2026-10-27
---
Apa yang dilihat dan dapat dilakukan setiap orang di EcoSystem berasal dari Role mereka. Role adalah kumpulan akses menu yang diberi nama. Seorang Employee dapat memiliki lebih dari satu Role dan memperoleh gabungan akses dari semuanya. Topik ini ditujukan untuk administrator.

## Siapa yang menggunakan

Administrator yang mengelola akses. Menu `Management` memiliki `Role` dan `Menu List`.

> [!WARNING]
> Perubahan akses langsung berdampak pada orang sungguhan. Bekerjalah dengan hati-hati, ubah satu Role dalam satu waktu, dan isi alasan bila diminta. Jangan mencabut akses dari Role yang Anda pakai sendiri.

## Sebelum Anda mulai

- Tentukan pekerjaan yang diwakili setiap Role, misalnya HR, Finance, atau Manager.
- Ketahui halaman apa saja yang dibutuhkan setiap Role. Untuk tiap halaman Anda dapat mengizinkan `View`, lalu `Create`, `Edit`, dan `Delete`. Beberapa halaman juga memiliki aksi khusus seperti `Approve` atau `Export`.
- Item `Global ESS` (halaman layanan mandiri Employee) selalu aktif untuk setiap Employee dan tidak dapat dimatikan.

## Langkah demi langkah

### Membuat atau mengubah Role

1. Buka `Management` › `Role`.
2. Klik `Add Role`, isi `Name` dan `Description`, lalu klik `Save`.
3. Daftar menampilkan jumlah `Members` untuk setiap Role.

### Mengatur akses sebuah Role

1. Pada daftar Role, buka `Menu access` untuk Role tersebut.
2. Cari halaman, tab, aksi, atau nama pada kotak pencarian, atau saring dengan `Granted`, `Not granted`, atau `Changed`.
3. Centang `View` agar Role dapat membuka halaman. Lalu centang `Create`, `Edit`, `Delete`, atau aksi khusus yang boleh dipakai Role. Mematikan `View` juga menghapus aksi pada baris itu.
4. Tab dicantumkan satu per satu di bawah halamannya, sehingga sebuah Role dapat diberi hanya sebagian tab.
5. Klik `Review & Save`, baca apa yang akan berubah, isi alasan (opsional), lalu konfirmasi. Tidak ada yang tersimpan sebelum langkah ini.

Alat bantu di halaman yang sama:
- `Compare…` menunjukkan perbedaan dua Role.
- `Copy from role…` menambahkan akses Role lain ke draf Anda dengan `Add selected to draft`.
- `Grant View to shown` dan `Revoke from shown` mengubah semua baris yang sedang tampil menurut pencarian atau filter.
- `History` mencantumkan setiap perubahan tersimpan beserta alasannya. `Undo` membatalkan satu perubahan.
- `Discard` membuang draf yang belum disimpan.

> [!IMPORTANT]
> Item yang sensitif meminta konfirmasi tambahan (`I understand and confirm`). Bacalah sebelum melanjutkan.

### Melihat siapa yang memegang sebuah menu (Menu List)

1. Buka `Management` › `Menu List`.
2. Cari menu dan buka tampilan `Access` untuk melihat Role mana saja yang memegangnya.
3. Berikan atau cabut akses untuk beberapa Role sekaligus, isi alasan, lalu `Save changes` setelah langkah `Review changes`.
4. `History` menampilkan perubahan sebelumnya pada menu tersebut.

### Memberi Role kepada seseorang

Role diberikan kepada setiap Employee dari data Employee (`Master` › `Employee`). Seseorang dapat memegang beberapa Role.

## Apa yang terjadi selanjutnya

Ketika akses sebuah Role disimpan, orang-orang dalam Role itu mendapatkan menu barunya. Daftar menu mereka diperbarui saat halaman dimuat berikutnya. Bila seseorang masih melihat menu lama, minta mereka keluar dan masuk kembali; paling lambat daftar lama kedaluwarsa setelah sekitar satu jam.

## Pertanyaan umum

**Seseorang mengatakan sebuah menu tidak ada.** Periksa apakah salah satu Role-nya memiliki `View` untuk menu itu. Lihat [[access-and-menus]].

**Bisakah saya membatalkan perubahan?** Bisa, gunakan `History` dan `Undo` pada halaman `Menu access` Role tersebut.

## Topik terkait

- [[access-and-menus]]
- [[ess-settings]]
- [[approval-workflows]]
- [[control-center]]

<!-- sources: routes/web.php, routes/api.php, app/Http/Controllers/RoleController.php, app/Http/Controllers/Management/RoleMenuAccessController.php, app/Services/Permissions/RoleMenuAccessService.php, app/Http/Middleware/ShareMenuPermissions.php, resources/views/management/roles/index.blade.php, resources/views/management/roles/menu-access.blade.php, resources/views/management/permissions/index.blade.php -->

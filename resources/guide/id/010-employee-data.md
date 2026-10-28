---
key: employee-data
chapter: human-capital
order: 10
title: Data Employee
summary: Cari, tambah, dan kelola data Employee, serta lihat profil yang sudah dikunci HR.
requires: [master.employee]
updated: 2026-10-27
---
Gunakan `Employee Data` untuk mencari Employee, menambah Employee baru, dan membuka profil lengkap beserta tab-tabnya (data dasar, alamat, identitas, bank, dan lainnya).

## Siapa yang menggunakan

Staf HR yang mengelola data induk Employee. Apa yang dapat Anda lakukan di halaman ini bergantung pada akses Anda: semua yang punya menu ini dapat mencari dan melakukan Export, sedangkan penambahan Employee dan menu aksi hanya tampil bila Anda punya izin yang sesuai.

## Sebelum Anda mulai

- Buka `Human Capital` › `Employee Data`.
- Untuk menambah Employee, siapkan ID Employee (ECI), email kerja, dan kata sandi awal.
- Jika Anda tidak melihat `Create Employee` atau menu aksi di kolom `Actions`, minta izin akses kepada administrator.

## Langkah demi langkah

### Mencari Employee

1. Ketik nama atau ECI di kotak pencarian pada bagian atas daftar. Tekan `/` untuk langsung menuju kotak itu.
2. Klik ikon corong di judul kolom untuk memfilter berdasarkan `Position`, `Module`, `Employee Group`, `Division`, `Department`, `Home Base`, `Status`, atau `Profile`.
3. Klik salah satu kartu ringkasan (`Total employees`, `Active`, `Blocked`, `Internship`, `Profile locked`) untuk memfilter daftar dengan sekali klik.
4. Pilih `Reset filters` untuk menghapus semua filter.
5. Pilih `Export Excel` untuk mengunduh daftar.

Kolom `Profile` menampilkan `Locked` bila HR sudah memverifikasi dan mengunci profil (lihat [[onboarding]]).

### Menambah Employee

1. Pilih `Create Employee`.
2. Isi `General Data`: `Employee` (ECI), `Password`, `Confirm Password`, `First Name`, `Nick Name`, dan `Work Email`. Kolom bertanda bintang merah wajib diisi.
3. Isi `Standard Address` dan `Organizational Data` (`Position`, `Employee Group`, `Division`, `Home Base`, dan seterusnya).
4. Pilih `Save`.

> [!NOTE]
> Consultant eksternal ditambahkan dari `External` › `External Consultants`. Lihat [[external-consultants]].

### Membuka dan memperbarui profil

1. Pilih baris seorang Employee.
2. Pilih tab, misalnya `Basic Data`, `Address`, `Identification`, `Family`, `Education`, `Qualification`, `Contract`, `Bank Account`, `Basic Payment`, atau `Attachment`. Anda hanya melihat tab yang diizinkan untuk akses Anda.
3. Ubah isian lalu simpan di dalam tab tersebut.

Spanduk di bagian atas memberi tahu bila profil terkunci. Employee tidak dapat mengubah profil yang terkunci, tetapi HR dapat. Untuk membukanya kembali bagi Employee, gunakan `Unlock` di halaman [[onboarding]].

### Aksi akun

Jika tombol aksi tampil pada sebuah baris, isinya:

- `Change Password` — mengatur kata sandi baru untuk orang tersebut.
- `Change ECI` — mengganti ID Employee; Anda mengonfirmasi dengan kata sandi Anda sendiri.
- `Change Role` — mencentang Role yang harus dimiliki orang tersebut.
- `Delete` — menghapus Employee setelah konfirmasi.

## Apa yang terjadi selanjutnya

Data yang Anda simpan dipakai untuk progres Onboarding, kesiapan [[contracts]], dan, bila diaktifkan, Payroll. Item yang kurang dilaporkan di sana, jadi pastikan identitas, rekening bank, dan dokumen tetap terbaru.

## Pertanyaan umum

**Mengapa ada tab yang tidak tampil?** Tab mengikuti akses Anda. Consultant juga tidak memiliki tab `Family`, `Contract`, atau `Basic Payment`.

**Bisakah saya mengubah profil yang terkunci?** Bisa, sebagai HR. Employee melihat bagian tersebut sebagai View Only sampai profil dibuka kembali.

## Topik terkait

- [[onboarding]]
- [[contracts]]
- [[external-consultants]]

<!-- sources: routes/web.php, resources/views/master/employee/index.blade.php, resources/views/master/employee/show.blade.php, resources/views/partials/sidebar.blade.php -->

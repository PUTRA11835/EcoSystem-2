---
key: guide-project-admin
chapter: reference
order: 88
title: Panduan Delivery Project Administrator
summary: Yang dipelihara project administrator: proyek dan item support, business partner, data karyawan, module group, dan ikhtisar konsultan.
requires: ["@role:Delivery Project Administrator"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Project Administrator. Anda menjaga data master dan catatan delivery tetap rapi agar tim proyek dapat bekerja.

## Siapa yang menggunakan

Delivery Project Administrator. Menu Anda mencakup `Delivery` › `Project` dan `Support`, `Master` (`Employee` dan `Business Partner`), `Management` (`Module Group` dan pengaturan karyawan), `Consultant Workload`, `Reporting` (`Collection Outlook`, `Collection Outlook (Support)` dan `Consultant Assignment`) dan `Calendar`.

## Sebelum Anda mulai

- Anda bekerja dengan data pribadi karyawan dan informasi kontak serta kredensial customer. Pakai hanya untuk tugas Anda dan jangan pernah menyalinnya ke chat, email, atau dokumen lain.
- Data master mengalir ke banyak halaman lain. Kode atau nama yang salah ikut menyebar, jadi periksa sebelum menyimpan.

## Langkah demi langkah

### Proyek dan item support

1. Buka `Delivery` › `Project` lalu pilih `Add New` untuk membuat proyek delivery. Isi formulir dan simpan, lalu buka proyeknya untuk melengkapi tab-tabnya.
2. Buka `Delivery` › `Support` lalu pilih `Add Delivery Support` untuk membuat item support.
3. Pilih satu baris untuk membuka item. Jaga `Team`, `Documents` dan tab lain tetap mutakhir, dan pakai `Create Folder` untuk folder OneDrive sebelum Anda mengunggah dokumen.

### Business partner

![Jendela Create Business Partner dengan data umum, alamat standar, dan data organisasi](images/guide/business-partner-create.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Master` › `Business Partner`. Pakai filter (`Status`, `Type`, `Business Partner`, `Customer Group`) lalu `Apply`, atau `Reset`.
2. Pilih `Create Business Partner`. Pilih `Type`, isi `Customer Code` (paling banyak 4 huruf atau angka) dan `Company Name`.
3. Isi `Email Domain` bila email masuk dari domain itu harus menjadi tiket customer ini.
4. Pilih `AE (Account Executive)` dari karyawan ber-role Sales, lalu simpan. Tambahkan kontak dan detail lain di halaman detail partner.
5. `View Grouping` menampilkan customer yang dikelompokkan menurut customer group.

### Data karyawan

![Daftar Employee Management](images/guide/employee-list.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Master` › `Employee`. Pilih `Create Employee` untuk menambah orang, atau `Export Excel` untuk mengunduh daftar.
2. Pakai `Manage Dropdown Data` untuk memelihara pilihan yang dipakai di formulir karyawan.
3. Buka seorang karyawan untuk memperbarui bagian datanya yang boleh diubah role Anda.

### Module group

![Daftar Module Group dengan tombol Add Group](images/guide/module-group.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Management` › `Module Group`. Sebuah group mengumpulkan modul yang terkait, misalnya Logistic.
2. Pilih `Add Group`, isi `Group Name` dan `Description`, centang `Modules`, lalu simpan.
3. Pakai `Edit` untuk mengubah group dan `Delete` untuk menghapus yang tidak dipakai.

### Konsultan dan penagihan

1. `Consultant Workload` menampilkan siapa yang sibuk. `Reporting` › `Consultant Assignment` mencantumkan proyek tiap konsultan, diambil dari tab `Team` proyek.
2. `Collection Outlook` dan `Collection Outlook (Support)` menampilkan perkiraan penagihan per bulan. Pilih periode lalu `Apply`, dan `Export Excel` untuk mengunduh.

## Apa yang terjadi selanjutnya

Catatan yang disimpan muncul di halaman yang memakainya. Misalnya business partner baru tersedia sebagai customer saat Anda membuat proyek.

## Pertanyaan umum

**Saya tidak dapat membuat business partner atau karyawan.** Tombolnya muncul hanya bila role Anda punya izinnya. Tanyakan kepada administrator.

**Kode customer ditolak.** Kode hanya boleh 4 huruf atau angka. Persingkat dan hapus karakter lain.

## Topik terkait

- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/delivery/project, resources/views/delivery/support, resources/views/master, resources/views/management/module-groups, resources/views/reporting/consultant-assignment.blade.php -->

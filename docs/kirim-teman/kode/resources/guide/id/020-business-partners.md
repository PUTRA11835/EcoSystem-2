---
key: business-partners
chapter: business-legal
order: 20
title: Business Partner
summary: Cari, buat, dan kelola customer dan vendor, serta kelompokkan dalam customer group.
requires: [master.customer]
updated: 2026-10-27
---
Business Partner adalah daftar induk customer dan vendor Anda. Setiap partner memiliki profil berisi alamat, kontak, identifikasi, rekening bank, dan lampiran, dan dapat dimasukkan ke dalam customer group.

## Siapa yang menggunakan

Staf Business, Finance, dan administrasi yang mengelola data customer dan vendor. Tab profil yang tampil, serta apakah Anda dapat mengubahnya, bergantung pada akses Anda. Bila sebuah tab tidak tampil atau terkunci, hubungi administrator.

> [!NOTE]
> Menu `Business Dev` ada, tetapi hanya menampilkan halaman "Coming Soon", sehingga tidak dibahas dalam panduan ini.

## Langkah demi langkah

### Mencari business partner

1. Buka `Business & Legal` › `Business Partner`.
2. Ketik email atau nama perusahaan pada kotak pencarian.
3. Persempit daftar dengan `Status` (`Active` atau `Blocked`), `Type` (`Customer` atau `Vendor`), atau `Customer Group`.
4. Klik baris untuk membuka profil. Partner yang ditandai untuk dihapus tampil sebagai `Flagged for Deletion`.

### Membuat business partner

1. Klik `Create Business Partner`.
2. Isi data umum, seperti `Company Name`, tipe (`Customer` atau `Vendor`), `Company Email`, `Industry Sector`, dan bahasa.
3. Isi alamat. Pilih negara, provinsi, kota, dan kecamatan secara berurutan. Anda juga dapat menautkan `Parent Customer` (hanya partner bertipe sama) dan `Customer Group`.
4. Klik `Create`. Partner baru muncul di daftar.

Di sini hanya kolom penting yang diminta. Lengkapi sisanya di profil.

### Melengkapi profil

1. Buka partner.
2. Pindah antar tab `Basic Data`, `Address`, `Contact Person`, `Identification`, `Bank Account`, `Credential`, dan `Attachment`.
3. Ubah isian lalu simpan. Tab yang boleh Anda lihat tetapi tidak boleh ubah tampil terkunci.

### Memakai customer group

1. Klik `View Grouping` pada daftar untuk membuka `Customer Grouping`.
2. Buat grup dengan nama grup, atau ubah dan hapus grup memakai ikon pada tiap grup.
3. Gunakan ikon anggota untuk menambahkan partner ke grup. Partner dalam grup yang sama ditampilkan bersama.

## Apa yang terjadi selanjutnya

Partner muncul di daftar dan dapat disaring berdasarkan tipe, status, dan grup. Perubahan terlihat oleh semua orang yang memiliki akses ke halaman ini.

## Pertanyaan umum

**Mengapa saya tidak bisa memilih parent?** Parent harus berupa business partner bertipe sama.

**Siapa yang dapat mengubah atau menghapus partner?** Hanya orang dengan akses yang sesuai. Bila Anda tidak bisa, hubungi administrator.

## Topik terkait

- [[employee-data]]
- [[legal-basis]]

<!-- sources: routes/web.php, routes/api.php, app/Http/Controllers/CustomerController.php, resources/views/master/customer/index.blade.php, resources/views/master/customer/show.blade.php, resources/views/master/customer/grouping.blade.php -->

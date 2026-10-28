---
key: user-guide-editor
chapter: administration
order: 130
title: User Guide Editor
summary: Ubah topik yang dibaca karyawan di User Guide, tambah topik baru, dan kembalikan teks aslinya.
requires: [control-center.user-guide]
updated: 2026-10-28
---
User Guide Editor memungkinkan administrator mengubah manual tanpa deployment. Topik bawaan EcoSystem tetap menjadi versi asli, sehingga Anda selalu dapat kembali.

## Siapa yang menggunakan

Administrator, dan role mana pun yang diberi menu `Control Center` › `User Guide Editor` di Menu Access.

## Sebelum Anda mulai

- Karyawan hanya melihat topik yang cocok dengan akses menu atau role mereka, sesuai `Visible to` pada topik. Periksa pengaturan itu sebelum Anda berharap sebuah topik sampai ke seseorang.
- Jangan pernah memasukkan password, kredensial, atau data nyata customer atau karyawan ke panduan.
- Perubahan berlaku untuk satu bahasa. Ubah bahasa Inggris dan Indonesia secara terpisah.

## Langkah demi langkah

### Cari topik

![Daftar User Guide Editor dengan satu baris per topik dan statusnya](images/guide/user-guide-editor-list.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Control Center` › `User Guide Editor`.
2. Pilih `English` atau `Bahasa Indonesia`. Setiap baris menampilkan judul, key, dan siapa yang dapat melihatnya.
3. Labelnya menunjukkan keadaan: `Default` (teks asli), `Edited` (Anda ubah), `Custom` (topik yang Anda tambah), `default changed` (teks asli berubah setelah Anda mengedit, jadi tinjau ulang) dan `not translated` (belum ada versi di bahasa ini).
4. Pilih `View` untuk membaca topik seperti yang dilihat karyawan, atau `Edit` untuk mengubahnya.

### Ubah topik

![Formulir ubah dengan kotak isi serta pengaturan penempatan dan visibilitas](images/guide/user-guide-editor-form.png)

*Tangkapan layar dengan data contoh.*

1. Ubah `Title`, `Summary`, dan isinya. Isi ditulis dalam Markdown. Pakai `## Judul` untuk bagian, dan tulis key topik di antara dua tanda kurung siku ganda untuk menautkan topik lain.
2. Buka tab `Preview` untuk melihat hasilnya sebelum menyimpan.
3. Di `Placement`, pilih `Chapter` dan `Order in chapter`. Angka kecil tampil lebih dulu.
4. Di `Visible to`, centang akses menu atau role yang boleh melihat topik. Biarkan semuanya kosong agar tampil untuk semua karyawan. `Designated approvers` menambahkan orang yang menyetujui dalam sebuah alur kerja.
5. Pilih `Save`. Pembaca langsung melihat perubahannya.

### Tambah topik

1. Pilih `New topic`, lalu isi `Key` (huruf kecil, angka, dan tanda hubung, misalnya `my-new-topic`). Key tidak dapat diubah kemudian.
2. Isi sisanya seperti di atas lalu simpan. Topik tampil dengan label `Custom`.

### Kembalikan ke asli

Pada topik yang diubah, pilih `Restore default` dan konfirmasi. Perubahan Anda dihapus dan teks asli kembali. Pada topik custom, tombol yang sama bertuliskan `Delete topic`.

## Apa yang terjadi selanjutnya

Karyawan melihat teks baru saat membuka User Guide berikutnya, juga di PDF. Setiap penyimpanan tercatat di `Audit Log` pada modul `User Guide`, tanpa isi artikelnya.

## Pertanyaan umum

**Perubahan saya tidak ditolak tetapi tidak ada yang melihatnya.** Periksa `Visible to`. Topik hanya sampai ke orang dengan akses menu atau role yang cocok.

**Editor menolak teks saya.** Pesannya menjelaskan sebabnya: em dash dan en dash, HTML mentah, tautan keluar, gambar yang bukan berkas `images/guide` yang ada, tautan ke topik yang tidak ada, atau menu atau role yang tidak dikenal.

**Saya ingin menambah gambar.** Gambar adalah berkas yang disimpan bersama aplikasi, di folder `public/images/guide`, dan tidak dapat diunggah di sini. Minta developer menambahkan berkasnya, lalu rujuk dengan sintaks gambar Markdown biasa dan path `images/guide` diikuti nama berkas.

**Daftar menulis `default changed`.** Teks asli diperbarui setelah Anda mengedit. Bandingkan, atau pakai `Restore default` untuk mengikuti teks asli yang baru.

<!-- sources: routes/web.php, app/Http/Controllers/UserGuideAdminController.php, app/Services/Guide/GuideTopicEditor.php, resources/views/admin/user-guide/index.blade.php, resources/views/admin/user-guide/form.blade.php -->

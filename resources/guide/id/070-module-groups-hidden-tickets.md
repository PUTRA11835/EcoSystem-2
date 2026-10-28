---
key: module-groups-hidden-tickets
chapter: administration
order: 70
title: Hidden Tickets & Module Group
summary: Tampilkan kembali Ticket yang disembunyikan ke daftar utama, dan kelompokkan module yang saling terkait.
requires: [management.hidden-tickets, management.module-groups]
updated: 2026-10-27
---
Dua halaman administrasi kecil mendukung ticketing: `Hidden Tickets`, tempat Ticket yang disembunyikan dari daftar utama dapat ditampilkan lagi, dan `Module Group`, tempat module yang saling terkait dikelompokkan. Topik ini ditujukan untuk administrator.

## Siapa yang menggunakan

Administrator proses ticketing. Setiap halaman memiliki akses sendiri.

## Langkah demi langkah

### Menampilkan kembali Ticket yang disembunyikan

1. Buka `Management` › `Hidden Tickets`.
2. Baca daftarnya. Daftar menampilkan nomor Ticket, deskripsi, `Customer`, `Ticket Lead`, `Priority`, `Status`, dan tanggal dibuat. Bila tertulis tidak ada Ticket tersembunyi, semuanya sudah tampil di daftar utama.
3. Pilih Ticket yang ingin dikembalikan.
4. Pada jendela konfirmasi, periksa Ticket lalu klik tombol tampilkan kembali.

> [!IMPORTANT]
> Setelah ditampilkan kembali, Ticket muncul di daftar utama untuk semua orang yang berhak melihatnya. Pastikan dulu bahwa Ticket memang boleh terlihat.

### Mengelompokkan module (Module Group)

Module Group mengumpulkan module yang saling terkait dengan satu nama, misalnya grup "Logistik" yang berisi beberapa module.

1. Buka halaman `Module Group`. Halaman ini mencantumkan `Group Name`, `Description`, `Modules` dalam grup, dan `Status`.
2. Klik `Add Group`.
3. Isi `Group Name` dan deskripsi, pilih module yang termasuk di dalamnya, lalu atur status menjadi `Active` atau `Inactive`.
4. Klik `Save`.
5. Untuk mengubah atau menghapus grup, gunakan ikon aksi pada barisnya lalu konfirmasi.

> [!NOTE]
> Halaman `Module Group` tidak tercantum di menu `Management` pada versi saat ini. Halaman ini hanya tersedia bagi orang yang memiliki aksesnya, jadi tanyakan kepada administrator Anda bila memerlukannya.

## Apa yang terjadi selanjutnya

Ticket yang ditampilkan kembali muncul di daftar Ticket utama. Module Group yang tersimpan dapat dipakai untuk menata module, misalnya pada tampilan beban kerja konsultan.

## Pertanyaan umum

**Siapa yang dapat menyembunyikan Ticket?** Menyembunyikan dilakukan dari Ticket itu sendiri oleh orang dengan aksesnya. Halaman ini hanya mengembalikan Ticket.

## Topik terkait

- [[tickets]]
- [[roles-and-menu-access]]

<!-- sources: routes/web.php, routes/api.php, app/Http/Controllers/HiddenTicketController.php, app/Http/Controllers/ModuleGroupController.php, app/Http/Controllers/TicketController.php, resources/views/management/hidden-tickets/index.blade.php, resources/views/management/module-groups/index.blade.php -->

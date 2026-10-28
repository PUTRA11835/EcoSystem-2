---
key: ticket-assignment
chapter: work-service
order: 12
title: Menugaskan ticket dan mengelola tim
summary: Ambil ticket, tentukan ticket lead, tambah anggota tim, dan kaitkan ticket ke satu Delivery Support.
requires: [ticket.assign-pic, ticket.assign-delivery-support, ui.ticket.manage-members, ticket.take]
updated: 2026-10-28
---
Setiap ticket butuh pemilik. Topik ini menunjukkan bagaimana ticket mendapat ticket lead dan timnya, serta dikaitkan ke sebuah Delivery Support.

## Siapa yang menggunakan

Helpdesk dan kepala yang menugaskan ticket, serta konsultan yang mengambil ticket sendiri. Setiap tombol hanya tampil bila role Anda mengizinkan.

## Sebelum Anda mulai

- Buka `Work & Service` › `Ticket`.
- Hanya orang yang diizinkan menjadi ticket lead atau anggota ticket yang ditawarkan di daftar. Ini diatur administrator, dan akun yang diblokir tidak pernah ditawarkan.

## Langkah demi langkah

### Mencari ticket tanpa pemilik

1. Buka tab `Unassigned` (tampil sebagai `Unassigned Ticket` pada sebagian role).
2. Buka ticket yang sesuai dengan Anda atau tim.

### Mengambil ticket sendiri

1. Buka ticket.
2. Pilih `Take This Ticket`.

Jika ticket belum jelas atau di luar keahlian Anda, tulis `Internal Note` untuk Helpdesk, jangan didiamkan.

### Menentukan ticket lead

![Halaman ticket dengan tombol Change Ticket Lead dan Assign to Delivery Support di panel kanan](images/guide/ticket-detail.png)

*Tangkapan layar dengan data contoh.*

1. Buka ticket lalu pilih `Assign Ticket Lead`. Bila ticket sudah punya lead, tombolnya bertuliskan `Change Ticket Lead`.
2. Pilih orangnya dan konfirmasi.

### Mengelola tim

1. Buka `Team Members` pada ticket.
2. Tambahkan rekan yang perlu ikut mengerjakan, atau keluarkan yang tidak lagi perlu.
3. Simpan perubahan.

### Mengaitkan ticket ke Delivery Support

1. Pilih `Assign to Delivery Support`.
2. Pilih Delivery Support milik customer yang tepat.

Sebuah ticket hanya milik **satu** Delivery Support. Jika salah kaitan, lepas dulu, lalu kaitkan yang benar.

> [!WARNING]
> `Hide Ticket` dan `Delete Ticket` hanya untuk role tertentu dan berdampak ke semua orang yang melihat ticket itu. Pakai hanya bila Anda yakin berwenang.

## Apa yang terjadi selanjutnya

Ticket muncul di tab `My Tickets` milik ticket lead dan anggota timnya, serta di `My Tasks`. Usulan mandays memakai keahlian ticket lead dan anggota, jadi jaga tim tetap akurat.

## Pertanyaan umum

**Seseorang tidak ada di daftar.** Ia mungkin tidak diizinkan menjadi ticket lead atau anggota, atau akunnya diblokir. Hubungi administrator.

**Saya tidak dapat mengubah Delivery Support.** Role Anda mungkin tidak mencakup `Assign to Delivery Support`. Hubungi Helpdesk atau kepala Anda.

<!-- sources: routes/web.php, app/Http/Controllers/TicketController.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php -->

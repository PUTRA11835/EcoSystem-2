---
key: ticket-mandays
chapter: work-service
order: 14
title: Customer Mandays dan Resolution Days
summary: Dua estimasi pada sebuah ticket, siapa yang menyetujui masing-masing, dan alurnya dari draft sampai disetujui.
requires: [ticket.propose-mandays-customer, ticket.propose-mandays-resolution, ticket.review-mandays, ticket.head-mandays, ticket.approve-resolution-days]
updated: 2026-10-28
---
Ticket yang membutuhkan estimasi pekerjaan membawa dua proposal yang berbeda. Keduanya mudah tertukar, jadi bedakan dengan baik.

## Siapa yang menggunakan

Ticket lead dan konsultan yang mengusulkan, Helpdesk yang meninjau Customer Mandays, dan kepala yang menyetujui Resolution Days. Tombol yang tampil bergantung pada role Anda.

## Sebelum Anda mulai

- Buka ticket dan cari bagian `Mandays`.
- Modul yang dapat dipilih pada proposal berasal dari keahlian ticket lead dan anggota tim, jadi pastikan dulu tim ticket sudah benar.

| | Customer Mandays | Resolution Days |
|---|---|---|
| Apa itu | Hari kerja yang disetujui **customer** | Estimasi internal untuk menyelesaikan ticket |
| Disusun oleh | Penanggung jawab ticket | Penanggung jawab ticket |
| Disetujui oleh | Customer, setelah ditinjau Helpdesk | Kepala |

## Langkah demi langkah

### Customer Mandays

![Halaman ticket dengan bagian Mandays: Propose Mandays, Propose Resolution Days, Review Mandays Proposal, dan Review Resolution Days](images/guide/ticket-detail.png)

*Tangkapan layar dengan data contoh.*

1. Pilih `Propose Mandays` lalu isi rinciannya. Simpan dulu sebagai `Draft`.
2. Periksa angkanya lalu kirim. Proposal lalu menunggu peninjauan.
3. Helpdesk memakai `Review Mandays Proposal` untuk memeriksa atau menyesuaikannya, lalu mengirimkannya ke customer lewat chat dan email.
4. Customer menyetujui lewat tautan yang diterima, dan proposal menjadi `Approved`.
5. Helpdesk dapat membatalkan proposal. Revisi menghasilkan versi baru.

### Resolution Days

1. Pilih `Propose Resolution Days`, isi rinciannya, dan simpan `Draft`.
2. Kirim. Statusnya menjadi `Pending Head`.
3. Kepala memakai `Review Resolution Days` untuk menyetujuinya, atau menolaknya disertai alasan.

## Apa yang terjadi selanjutnya

Hari yang disetujui dipakai untuk beban kerja di `My Tasks` dan `Consultant Workload`, serta untuk kuota pada laporan `MD Validation`. Proposal Resolution Days yang ditolak diperbaiki lalu dikirim lagi.

## Pertanyaan umum

**Yang mana yang saya butuhkan?** Pakai Customer Mandays untuk yang dibayar atau disetujui customer, dan Resolution Days untuk rencana internal.

**Saya tidak melihat tombol peninjauan.** Meninjau dan menyetujui hanya untuk Helpdesk dan kepala. Hubungi administrator bila Anda merasa seharusnya memilikinya.

<!-- sources: routes/web.php, app/Http/Controllers/MandaysController.php, resources/views/ticket/show.blade.php, resources/views/ticket/task.blade.php -->

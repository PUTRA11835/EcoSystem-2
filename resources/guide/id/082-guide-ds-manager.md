---
key: guide-ds-manager
chapter: reference
order: 82
title: Panduan Delivery Support Manager
summary: Menjalankan support sehari-hari: buat dan tugaskan ticket, tinjau mandays, ikuti SLA, dan kelola Delivery Support.
requires: ["@role:Delivery Support Manager", "@role:Delivery Support Manager II"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Support Manager. Anda menjalankan pekerjaan support sehari-hari: siapa mengerjakan apa, kualitas proposal, dan kesehatan layanan.

## Siapa yang menggunakan

Manager Delivery Support. Beberapa aksi, seperti menyetujui timesheet, justru milik kepala.

## Sebelum Anda mulai

- Menu Anda mengikuti role. Tombol yang tidak ada bukan bagian role Anda.
- Mengubah status, prioritas, atau tipe ticket, atau menghapus Delivery Support, berdampak ke laporan dan SLA. Pastikan dulu perubahannya dan tulis `Internal Note` berisi alasannya.
- Informasi credential customer pada ticket tidak boleh disalin ke chat, catatan, email, atau dokumen lain.

## Langkah demi langkah

### Rutinitas yang disarankan

1. Buka tab `All Tickets` dan cari ticket yang tidak bergerak atau mendekati batas SLA.
2. Buka `Ticket Validation` dan proses ticket masuk: setujui permintaan yang sah, tolak sisanya disertai alasan.
3. Beri pemilik pada ticket yang belum bertuan. Periksa dulu `Consultant Workload` agar pekerjaan terbagi adil. Lihat [[ticket-assignment]].
4. Tinjau proposal: `Review Mandays Proposal` untuk Customer Mandays dan `Review Resolution Days` untuk estimasi internal. Lihat [[ticket-mandays]].
5. Ikuti `SLA` › `SLA Report`, dan buka log SLA sebuah ticket bila perlu menelusuri selisih.

### Membuat ticket

![Daftar ticket dengan kartu status, tab My Tickets dan All Tickets, serta tombol Create Ticket](images/guide/ticket-list.png)

*Tangkapan layar dengan data contoh.*

1. Pilih `Create Ticket` pada daftar ticket. Formulirnya berjudul `Create New Ticket`.

![Formulir Create New Ticket dengan customer, to, cc, subject, priority, ticket type, scale, dan additional info](images/guide/ticket-create-modal.png)

*Tangkapan layar dengan data contoh.*

2. Pilih `Customer`. `TO` dan `CC` bersifat opsional.
3. Tulis `Subject / Description`, lalu atur `Priority` dan `Ticket Type`. Atur `Scale` bila penting untuk SLA.
4. Di bawah `Additional Info` Anda dapat menambahkan `Name` kontak, `No HP`, `Module`, dan `Client`. Tambahkan `Message` dan lampiran bila berguna.
5. Pilih tombol buat di bagian bawah formulir. Jangan menaruh password atau credential di ticket.

### Mengelola Delivery Support

![Halaman Delivery Support dengan kartu ringkasan dan daftar item support](images/guide/delivery-support-list.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Delivery` › `Support`.
2. Anda dapat mengubah semua bagian: informasi umum, persetujuan, keuangan, tim, customer PIC, aktivitas, SLA, biaya rencana, dokumen, dan recons.
3. Anda dapat menambah Delivery Support, menghapus yang tidak terpakai, dan melepas ticket yang salah kaitan. Sebuah ticket hanya milik satu Delivery Support, jadi kaitkan lagi ke yang benar setelahnya.

### Laporan dan kalender

1. Baca `MD Validation`, `MD Recap`, `Diagram Report`, dan `Log Shifting` di `Reporting`.
2. Buat event di `Calendar` › `Events`, misalnya untuk rapat tim.
3. Catat waktu Anda sendiri di `Calendar` › `Timesheets`. Lihat [[calendar-timesheet]].

## Apa yang terjadi selanjutnya

Keputusan Anda tampak di riwayat ticket dan di laporan. Menghapus Delivery Support atau melepas ticket mengubah angka SLA dan laporan.

## Pertanyaan umum

**Siapa yang menyetujui timesheet?** Kepala. Anda tetap dapat mencatat timesheet sendiri.

**Sebuah ticket terkait ke Delivery Support yang salah.** Lepas lalu kaitkan yang benar. Lihat [[ticket-assignment]].

## Topik terkait

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/staging/index.blade.php, resources/views/admin/sla/report.blade.php, resources/views/delivery/support/list/show.blade.php -->

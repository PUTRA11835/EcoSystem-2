---
key: guide-section-head
chapter: reference
order: 95
title: Panduan Delivery Support Section Head
summary: Tugas section head support: tugaskan dan tinjau tiket, setujui mandays, kelola item support, dan baca login log.
requires: ["@role:Delivery Support Section Head"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Support Section Head. Anda memimpin satu seksi tim support dan mengambil keputusan atas tiketnya.

## Siapa yang menggunakan

Delivery Support Section Head. Menu Anda mencakup `Ticket`, `Consultant Workload`, `Calendar`, `Reporting` (`Ticketing Overview` dan `Ticket by Modul`), `Master` (`Employee` dan `Business Partner`), `Delivery` › `Support`, `SLA` › `SLA Report` dan `Control Center` (`Overview`, `Activity Log`, `Active Sessions` dan `Login Log`).

## Sebelum Anda mulai

- Keputusan Anda berdampak pada penagihan dan beban kerja. Baca rinciannya sebelum menyetujui, dan tulis alasan yang jelas saat menolak.
- `Login Log` dan data master memuat informasi pribadi. Pakai hanya bila perlu.

## Langkah demi langkah

### Tiket

1. Buka `Ticket` dan tab yang Anda butuhkan. Tetapkan lead dan anggota tim, atau ambil sendiri sebuah tiket. Lihat [[ticket-assignment]].
2. Buka `Consultant Workload` sebelum menugaskan pekerjaan, untuk melihat siapa yang sibuk.
3. Tinjau usulan Customer Mandays dan setujui Resolution Days. Lihat [[ticket-mandays]].
4. Sembunyikan tiket dari daftar utama, atau hapus yang dibuat karena salah, dengan tombol pada tiket.

### Item support

1. Buka `Delivery` › `Support`.
2. Kerjakan bagian yang boleh diubah role Anda: informasi umum, persetujuan, finansial, tim, aktivitas, SLA, plan cost, dan dokumen.

### Laporan dan waktu

1. `Reporting` › `Ticketing Overview` dan `Ticket by Modul` menampilkan angka tiket. `SLA` › `SLA Report` menampilkan waktu respons dan penyelesaian.
2. Pakai `Calendar` untuk event dan `Timesheets`. Lihat [[calendar-timesheet]].

### Login Log

`Control Center` › `Login Log` mencantumkan masuk dan keluar terbaru setiap karyawan, dengan perangkat, alamat IP, dan lokasi. Pakai `Last Login` untuk melihat siapa yang baru masuk.

## Apa yang terjadi selanjutnya

Item yang disetujui berlanjut dan pemintanya diberi tahu. Item yang ditolak kembali kepada mereka beserta alasan Anda.

## Pertanyaan umum

**Saya tidak melihat tombol yang dijelaskan di sini.** Itu bukan bagian dari role Anda.

## Topik terkait

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/delivery/support, resources/views/admin/login-log.blade.php -->

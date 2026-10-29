---
key: guide-operation-director
chapter: reference
order: 91
title: Panduan Delivery Operation Director
summary: Yang diikuti delivery operation director: proyek, support, konsultan, penagihan, SLA, dan laporan manajemen.
requires: ["@role:Delivery Operation Director"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Operation Director. Anda mengawasi delivery secara keseluruhan: proyek, support, orang, dan hasilnya.

## Siapa yang menggunakan

Delivery Operation Director. Anda punya menu terluas di antara role delivery, termasuk `Ticket`, `Consultant Workload`, `Calendar`, banyak halaman `Reporting`, `Master` (`Employee` dan `Business Partner`), `Delivery` (`Project` dan `Support`), `SLA` dan `RPMO`.

## Sebelum Anda mulai

- Anda dapat melihat pendapatan, biaya, kontrak, dan data pribadi. Pakai hanya untuk pekerjaan Anda dan jangan pernah menyalinnya ke chat atau email.
- Menutup atau menghapus proyek berdampak pada banyak orang. Baca rinciannya lebih dulu.

## Langkah demi langkah

### Pantau delivery

1. Buka `Delivery` › `Project` dan `Delivery` › `Support`. Kartu menampilkan berapa item yang terbuka, berisiko, atau segera berakhir, dan klik menyaring daftar. Anda dapat membuat item dengan `Add New`, dan menutup atau menghapus proyek dari halamannya.
2. Buka `Ticket` untuk gambaran support dan `SLA` › `SLA Report` untuk waktu respons dan penyelesaian.
3. Buka `Consultant Workload` untuk melihat siapa yang sibuk.

### Baca laporan

1. `Reporting` › `Ticketing Overview`, `Ticket by Modul` dan `MD Validation` menampilkan angka tiket dan mandays. `Diagram Report` menampilkan data tiket sebagai grafik. `Log Shifting` mencantumkan catatan SLA antar-shift.
2. `Reporting` › `Collection Outlook` dan `Collection Outlook (Support)` menampilkan perkiraan penagihan per bulan.
3. `Reporting` › `Customer MD` mencantumkan change request dan mandays customer. Lihat [[customer-md]].
4. `Reporting` › `Consultant Assignment` dan `Resource Timeline` menampilkan penempatan konsultan. Lihat [[resource-timeline]].
5. `Reporting` › `Weekly Consolidation` berisi rekap mingguan tiket terbuka per modul. Lihat [[weekly-consolidation]].

### Waktu dan orang

Pakai `Calendar` untuk event dan `Timesheets`. Lihat [[calendar-timesheet]]. `Master` › `Employee` dan `Business Partner` menyimpan data master.

### RPMO

`RPMO` menampilkan halaman modul, yang mengarah ke `Period Management`.

## Apa yang terjadi selanjutnya

Keputusan yang Anda ambil pada proyek dan pengaturan berlaku bagi semua orang yang bekerja dengan catatan itu. Angka laporan diperbarui dari data langsung.

## Pertanyaan umum

**Saya tidak menemukan sebuah laporan.** Hanya laporan di menu `Reporting` Anda yang menjadi bagian dari role Anda.

## Topik terkait

- [[customer-md]]
- [[resource-timeline]]
- [[weekly-consolidation]]
- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/delivery, resources/views/reporting, resources/views/rpmo/rpmo.blade.php -->

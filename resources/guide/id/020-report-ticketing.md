---
key: report-ticketing
chapter: reporting
order: 20
title: Laporan Ticket dan Timesheet
summary: Apa yang ditampilkan tiap laporan Support dan cara membacanya.
requires: [reporting.ticketing-overview, reporting.ticket-by-module, reporting.resolution-days, reporting.validation, reporting.md-recap, reporting.log-shifting]
updated: 2026-10-27
---
Kelompok `Support Reports` mencakup Ticket, pesan SLA, dan mandays yang dicatat di Timesheet. Topik ini menjelaskan tujuan tiap laporan. Untuk langkah bersama seperti filter dan Export, lihat [[reports-overview]].

## Siapa yang menggunakan

Manajer delivery dan support, kepala bagian, helpdesk, dan finance. Anda hanya melihat laporan yang boleh dibuka oleh role Anda.

## Daftar laporan

### Ticketing Overview

Menampilkan `Customer Tickets`: satu baris per customer dengan `Total Mandays` dan jumlah Ticket menurut `Ticket Status` (`Open`, `In Process`, `Close`, `Other`, `Wait Close`). Gunakan kotak pencarian untuk menemukan customer. Buka sebuah customer untuk melihat Ticket-nya, yang dapat disaring menurut nomor Ticket, deskripsi, status, dan lead, serta diurutkan menurut `Create Date` atau `Day Not Close`.

### Ticket by Module

Halamannya berjudul `Ticket by Modul`. Laporan ini mengelompokkan Ticket menurut modul dalam bentuk kartu. Saring menurut nomor Ticket, deskripsi, `Status`, `Ticket Lead`, dan `Modul`, lalu gunakan `Expand All` atau `Collapse All`. Kolomnya antara lain `Created At` dan `Day not Close`. Klik `Export` untuk berkas Excel, dan `Clear Filters` untuk mengatur ulang.

### Resolution Days

Menampilkan Ticket yang resolution days-nya belum disetujui. Kartu `Total Unapproved`, `None` (belum pernah diusulkan), dan `Pending Head` (menunggu persetujuan) menyaring tabel. Klik sebuah baris untuk membuka `Review Resolution Days`, ubah `Approved Days` dan `Approve Add.`, lalu klik `Save Approval`. `Open Ticket` membawa Anda ke Ticket tersebut. Meninjau memerlukan akses yang sesuai.

### MD Validation

Berjudul `MD Validation` dengan subjudul `Timesheet Report`. Laporan ini membandingkan mandays yang terpakai di Timesheet dengan kuota. Kartunya menampilkan `Total Entries`, `Match` (terpakai sama dengan kuota), `Less` (di bawah kuota), dan `Over` (di atas kuota); klik salah satu untuk menyaring. Filter lainnya adalah `Employee`, `Date`, `Ticket`, `Customer`, `MD Status`, dan `Approval` (`Approved`, `Submitted`, atau `Draft`). Kolom `MD Input`, `Quota MD`, dan `Remain` menunjukkan angkanya. Klik `Export MD` untuk mengunduh.

### MD Recap

Menampilkan mandays yang disetujui per pegawai dan mode. Pilih bulan dan tahun (atau `All Months` dan `All Years`). Kartunya memuat `Total MD`, `Employees`, `OnSite MD`, dan `Remote MD`. Saring menurut `Name` dan `Mode` (`OnSite` atau `Remote`). Gunakan `Export MD Recap`, atau `Export Resolution Days` untuk berkas resolution days.

### Log Shifting

Berjudul `Log Shifting`, dengan subjudul yang menyebut ringkasan pesan SLA per Ticket. Saring menurut nomor Ticket, deskripsi, `PIC`, dan `Created At`. Buka sebuah Ticket untuk melihat tiap pesan beserta `Date`, `Time`, `SLA Message`, dan `PIC`.

## Apa yang terjadi selanjutnya

Angka berubah seiring Ticket diperbarui dan Timesheet disetujui. Muat ulang laporan untuk melihat angka terbaru.

## Pertanyaan umum

**`MD Validation` menampilkan `Over` untuk sebuah Ticket.** Mandays yang dicatat melebihi kuota. Periksa catatannya bersama ticket lead.

**`MD Recap` kosong.** Hanya Timesheet yang sudah disetujui yang dihitung, jadi periksa bulan dan tahunnya.

## Topik terkait

- [[reports-overview]]
- [[report-delivery]]
- [[tickets]]
- [[sla]]

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/ticketing-overview.blade.php, resources/views/reporting/ticket-by-module.blade.php, resources/views/reporting/resolution-days.blade.php, resources/views/reporting/reporting.blade.php, resources/views/reporting/md-recap.blade.php, resources/views/reporting/log-shifting.blade.php -->

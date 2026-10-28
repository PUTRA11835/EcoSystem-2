---
key: report-delivery
chapter: reporting
order: 30
title: Laporan penagihan, penugasan, dan grafik
summary: Baca Collection Outlook, Consultant Assignment, dan Diagram Report.
requires: [reporting.collection-outlook, reporting.collection-outlook-support, reporting.consultant-assignment, reporting.diagram-report]
updated: 2026-10-27
---
Laporan ini mengikuti arus penagihan dan orang-orang yang bekerja di delivery, serta memberi gambaran visual tentang Ticket. Untuk filter dan Export secara umum, lihat [[reports-overview]].

## Siapa yang menggunakan

Finance, account executive, kepala delivery, dan manajer. Anda hanya melihat laporan yang boleh dibuka oleh role Anda.

## Daftar laporan

### Collection Outlook

Berada di `Project Reports`. Laporan ini menampilkan rencana penagihan berdasarkan Term Of Payment tiap Project, per Project dan bulan.

1. Buka `Reporting` › `Project Reports` › `Collection Outlook`.
2. Atur bulan `From` dan `To`, dan bila perlu `Account Executive`, lalu klik `Apply`.
3. Baca kartu `Total Outlook`, `Paid`, `Unpaid`, dan `Payment Terms`. Jumlah yang sudah dibayar diberi warna hijau dan yang belum dibayar berwarna merah.
4. Baca tabelnya, dengan kolom `Customer`, `Project Name`, `TOP No.`, dan satu kolom per bulan.
5. Buka sebuah entri penagihan untuk melihat `Term Of Payment Details`: informasi termin, tanggal dan nomor Invoice, serta jumlah penagihan.
6. Untuk mencatat pembayaran, klik `Edit Status`, pilih `Open`, `Paid`, atau `Delay`, isi `Paid Date` dan `Invoice Number` (bertanda bintang), lalu klik `Save Changes`. Tombol ubah hanya tampil bagi orang yang berhak `Edit Payment Status`.
7. Klik `Export Excel` untuk mengunduh.

Jumlah dan persentase tiap termin dikelola di [[delivery]], bukan di sini.

### Collection Outlook (Support)

Berada di `Support Reports`. Cara kerjanya sama, tetapi untuk kontrak Support, dengan `Support Name` sebagai pengganti `Project Name`, dan filter `Type` sebagai pengganti `Account Executive`.

### Consultant Assignment

Berada di `Project Reports`. Laporan ini mendaftar Consultant yang ditugaskan pada Project delivery, diambil dari `Team` setiap Project.

1. Buka `Consultant Assignment`.
2. Baca kartunya: `Assignments`, `Consultants`, `Projects`, `Active`, `Upcoming`, `Ended`, `Internal`, `External / Vendor`, `Planned MD`, dan `Actual MD`. Klik sebuah kartu untuk menyaring.
3. Saring menurut `Consultant`, `ECI`, `Position`, `Project`, `IO Number`, `Customer`, `Project Category`, `Module`, `Project Role`, `Employee Type`, `Vendor`, dan `Period`. Klik `Reset Filters` untuk menghapus.
4. Klik `Expand All` untuk membuka penugasan semua Consultant, dan gunakan `Prev` serta `Next` untuk halaman lain.
5. Klik `Export` untuk mengunduh.

### Diagram Report

Satu halaman berisi sepuluh grafik tentang Ticket, seperti Ticket per modul, tipe Ticket per bulan, change request dibandingkan Ticket lain, dan Ticket yang ditutup per bulan.

1. Buka `Reporting` › `Diagram Report`.
2. Pilih `Customer` dan `Period` (`Start Date` dan `End Date`), lalu klik `Apply`.
3. Gulir melihat grafik `Chart 1` sampai `Chart 10`. Klik `Clear Filter` untuk kembali ke seluruh data.

## Apa yang terjadi selanjutnya

Outlook mencerminkan status tiap termin segera setelah disimpan. Angka penugasan mengikuti perubahan tim Project.

## Pertanyaan umum

**Saya tidak bisa mengubah status pembayaran.** Hak ini diberikan terpisah. Hubungi administrator.

**Sebuah Project tidak muncul di `Collection Outlook`.** Laporan hanya mendaftar Project yang memiliki Term Of Payment dalam rentang bulan yang dipilih.

## Topik terkait

- [[reports-overview]]
- [[report-ticketing]]
- [[delivery]]

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/collection-outlook.blade.php, resources/views/reporting/collection-outlook-support.blade.php, resources/views/reporting/consultant-assignment.blade.php, resources/views/reporting/diagram-report.blade.php -->

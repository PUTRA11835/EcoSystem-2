---
key: external-timesheet-payment
chapter: external
order: 50
title: Timesheet & Payment
summary: Buka dan tinjau Timesheet Consultant, verifikasi Invoice, catat pembayaran, dan atur Approver.
requires: [general.external.timesheet, general.external.timesheet.invoices, general.external.timesheet.settings]
updated: 2026-10-27
---
`Timesheet & Payment` mengikuti satu bulan kerja Consultant: dari jam yang dicatat, persetujuan, Invoice, hingga pembayaran. Ada tiga tab: `Timesheets`, `Invoices & Payments`, dan `Settings`.

## Siapa yang menggunakan

HR dan Approver yang ditetapkan di `Settings`. Consultant mengisi Timesheet sendiri di `My Timesheet & Invoice`, lihat [[my-timesheet-invoice]].

## Sebelum Anda mulai

- Buka `External` › `Timesheet & Payment`. Anda melihat tab yang diizinkan untuk akses Anda.
- Timesheet dimiliki oleh bulan yang tercakup dalam Contract Consultant yang `Active`, lihat [[external-contracts]].
- Siapa yang menyetujui atau memverifikasi diatur di `Settings`. Menyetujui, mengembalikan, dan memverifikasi mengikuti Approver tersebut, bukan izin umum.
- Nilai uang hanya ditampilkan bagi orang yang punya izin melihatnya.

## Langkah demi langkah

### Membuka Timesheet

1. Di `Timesheets`, pilih `New Timesheet`.
2. Pilih `External consultant` dan `Month`. Hanya bulan yang tercakup dalam Contract Consultant dan belum memiliki Timesheet yang ditawarkan.
3. Pilih `Create & open`.

Gunakan ini untuk mengisi jam atas nama Consultant. Consultant melihat Timesheet tersebut di `My Timesheet & Invoice`.

### Mengisi jam kerja

1. Pilih `Add row` lalu isi tanggal, Project, jam mulai dan selesai, jam kerja, lokasi (`Onsite`, `Remote`, atau `Hybrid`), dan aktivitas. Jam mulai dan selesai mengisi jam kerja; tanpa keduanya, ketik jamnya.
2. Tanggal harus berada dalam bulan tersebut, dalam periode Contract, dan tidak boleh di masa depan.
3. Pilih `Save draft`, lalu `Submit for approval`.

Lembar menampilkan `Total hours`, `Billable quantity`, tarif, dan jumlah kotor. Untuk Contract harga tetap, Anda juga mengisi bagian harga yang ditagih pada Timesheet ini.

### Menyetujui atau mengembalikan

1. Buka Timesheet yang `Submitted`. Jika Anda Approver, Anda melihat `Approve timesheet` dan `Return for correction`.
2. Pilih `Approve`, atau ketik `Reason (required)` lalu pilih `Return`.

Statusnya `Draft`, `Submitted`, `Returned`, dan `Approved`. Persetujuan mengunci Timesheet dan memungkinkan Consultant mengunggah Invoice. Timesheet yang dikembalikan dapat diperbaiki dan diajukan lagi.

### Memverifikasi Invoice dan mencatat pembayaran

1. Buka `Invoices & Payments`. Halaman ini mendaftar Timesheet yang disetujui beserta Invoice-nya.
2. Buka salah satu dan gunakan `View invoice` untuk membaca Invoice. Status Invoice adalah `Waiting verification`, `Verified`, `Rejected`, atau `Paid`.
3. Pilih `Verify invoice`, atau ketik alasan lalu pilih `Reject`.
4. Setelah pembayaran, pilih `Mark as paid` dan isi `Payment date` serta `Payment reference`.

Daftar `History` di halaman mencatat setiap langkah.

### Mengatur Approver

1. Buka `Settings`.
2. Di bawah dua tahap, `Timesheet approval` dan `Invoice verification`, pilih `Add step`. Beri nama langkahnya dan pilih Role atau orangnya.
3. Untuk kasus khusus, tambahkan `Exception for a consultant` agar orang lain yang menyetujui Consultant tersebut.
4. Di `Options`, tentukan apakah Approver boleh menyetujui Timesheet-nya sendiri (nonaktif secara bawaan) dan atur `Back-fill limit (days)`, yaitu seberapa jauh ke belakang Consultant boleh mencatat hari kerja (0 berarti tanpa batas). Pilih `Save options`.

Siapa pun yang tercantum pada suatu langkah boleh bertindak pada tahap itu. Perubahan berlaku untuk Timesheet yang diajukan sesudahnya; Timesheet yang sudah menunggu tetap memakai Approver saat diajukan.

## Apa yang terjadi selanjutnya

Setelah pembayaran, Invoice berstatus `Paid`. Consultant melihat setiap status di `My Timesheet & Invoice`.

## Pertanyaan umum

**Mengapa saya tidak bisa menyetujui?** Anda tidak tercantum sebagai Approver untuk Consultant tersebut, atau Timesheet itu milik Anda dan pilihan menyetujui milik sendiri nonaktif.

**Mengapa tidak ada bulan yang ditawarkan?** Consultant tidak memiliki Contract Active untuk bulan itu, atau sudah memiliki Timesheet.

## Topik terkait

- [[my-timesheet-invoice]]
- [[external-contracts]]
- [[external-consultants]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/timesheet/index.blade.php, resources/views/hr-general/external/timesheet/components/sheet.blade.php, resources/views/hr-general/external/timesheet/settings.blade.php, app/Support/External/ExternalTimesheetRules.php -->

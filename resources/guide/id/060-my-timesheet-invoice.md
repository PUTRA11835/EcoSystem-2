---
key: my-timesheet-invoice
chapter: external
order: 60
title: My Timesheet & Invoice
summary: Catat jam kerja Anda tiap bulan sebagai Consultant eksternal, ajukan, lalu unggah Invoice Anda.
requires: [general.external.my-timesheet]
updated: 2026-10-27
---
`My Timesheet & Invoice` adalah halaman milik Consultant. Anda mencatat jam kerja untuk setiap bulan Contract Anda, mengajukannya untuk disetujui, lalu mengunggah Invoice setelah Timesheet disetujui.

## Siapa yang menggunakan

Consultant eksternal. Staf internal memakai `My Timesheet` di kalender.

## Sebelum Anda mulai

- Buka `My Timesheet & Invoice` dari sidebar.
- Anda memerlukan Contract eksternal yang aktif. Jika muncul `No active contract yet`, Timesheet Anda terkait dengan Contract Anda; ketika HR mengaktifkannya, bulan-bulannya muncul di sini.
- Halaman menampilkan empat langkah: catat jam, persetujuan, Invoice, pembayaran.

## Langkah demi langkah

### Memulai satu bulan

1. Di `Months of your contract`, cari bulan yang Anda inginkan.
2. Pilih bulan tersebut. Jika belum memiliki Timesheet, memilihnya akan memulai satu. Bulan yang sudah memilikinya menampilkan statusnya.

### Mencatat jam kerja

1. Pilih `Add row`.
2. Isi tanggal, Project (opsional), jam mulai dan selesai, lokasi, serta apa yang Anda kerjakan. Jam kerja dihitung dari jam mulai dan selesai; tanpa keduanya, ketik jamnya.
3. Ulangi untuk setiap hari Anda bekerja.
4. Pilih `Save draft` untuk menyimpan pekerjaan Anda.

Tanggal harus berada dalam bulan tersebut, dalam periode Contract Anda, dan tidak boleh di masa depan. Jika HR menetapkan batas pengisian mundur, Anda hanya dapat mencatat hari sejauh jumlah hari tersebut ke belakang. Halaman menampilkan `Total hours`, jumlah yang dapat ditagih, dan perkiraan nilainya bila Anda diizinkan melihat nilai uang.

### Mengajukan untuk persetujuan

1. Periksa baris-barisnya. Untuk Contract harga tetap, isi bagian harga yang Anda tagih pada Timesheet ini.
2. Pilih `Submit for approval`.

Statusnya menjadi `Submitted`. Anda tidak dapat mengubah baris selama menunggu. Jika Approver mengembalikannya, statusnya `Returned` disertai alasan. Perbaiki baris lalu ajukan lagi.

### Mengunggah Invoice

1. Ketika Timesheet `Approved`, formulir Invoice terbuka.
2. Isi `Invoice number`, `Invoice date`, dan `Amount`.
3. Lampirkan `Invoice (PDF)` lalu pilih `Upload invoice`.

Status Invoice adalah `Waiting verification`, `Verified`, `Rejected`, atau `Paid`. Jika `Rejected`, baca alasannya lalu pilih `Upload corrected invoice`. Bila `Paid`, tanggal dan referensi pembayaran ditampilkan.

Anda dapat mencetak Timesheet dari halaman, dan melihat Invoice yang sudah Anda unggah.

## Apa yang terjadi selanjutnya

Approver yang ditetapkan memeriksa jam Anda, lalu Invoice Anda diverifikasi dan dibayar dalam jangka waktu pembayaran pada Contract Anda. Setiap langkah tercatat di `History` pada halaman.

## Pertanyaan umum

**Mengapa ada bulan yang tidak tampil?** Bulan berasal dari periode Contract Anda. Tanyakan kepada HR bila tanggal Contract Anda salah.

**Saya salah mengajukan.** Minta Approver mengembalikannya, lalu perbaiki.

**Siapa yang menyetujui Timesheet saya?** HR menetapkan Approver untuk tiap Consultant. Halaman menunjukkan siapa yang sedang ditunggu.

## Topik terkait

- [[external-timesheet-payment]]
- [[external-contracts]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/timesheet/my.blade.php, resources/views/hr-general/external/timesheet/components/sheet.blade.php, app/Support/External/ExternalTimesheetRules.php, resources/views/partials/sidebar.blade.php -->

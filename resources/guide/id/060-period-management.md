---
key: period-management
chapter: work-service
order: 60
title: Period Management
summary: Buka dan tutup periode pelaporan bulanan, atur bagian tiap tim, dan putuskan permintaan akses terlambat.
requires: [rpmo.periods]
updated: 2026-10-28
---
Period Management mengatur periode pelaporan bulanan tempat orang boleh mengisi timesheet. Yang dapat Anda lakukan di halaman ini bergantung pada izin yang dimiliki role Anda di Menu Access.

## Siapa yang menggunakan

| Izin di Menu Access | Yang dapat Anda lakukan | Diberikan secara bawaan kepada |
|---|---|---|
| `Period Management` (halamannya) | Membuka halaman: tampilan hanya baca dengan `Audit Log` | Beberapa role |
| `Manage Global` | Membuat periode, mengubah tanggalnya, menghapusnya, membuka dan menutupnya secara global, menutup paksa bagian sebuah tim, memutuskan permintaan akses terlambat pada langkah RPMO | EC Administrator, Delivery RPMO Head |
| `Manage Project Domain` | Membuka dan menutup bagian Project, memutuskan permintaan akses terlambat tim Project pada langkah pertama | EC Administrator, Delivery Project Head |
| `Manage Support Domain` | Membuka dan menutup bagian Support, memutuskan permintaan akses terlambat tim Support pada langkah pertama | EC Administrator, Delivery Support Head |

Administrator dapat memberi atau mencabut tiap izin untuk role mana pun di `Control Center` › `Menu Access`. Orang yang punya kedua izin domain mendapat tombol untuk kedua bagian.

## Sebelum Anda mulai

- Sebuah periode biasanya berjalan dari tanggal 21 satu bulan sampai tanggal 20 bulan berikutnya, seperti periode yang sudah ada.
- Periode punya status global (`Global (RPMO)`) dan satu status untuk tiap tim (`Project Domain` dan `Support Domain`). Setiap status berupa `Not Open`, `Open`, atau `Closed`.
- Orang hanya dapat mencatat timesheet pada periode yang terbuka. Bila terlewat, mereka meminta akses terlambat, dan Anda memutuskannya di sini.

## Langkah demi langkah

### Membaca halaman

![Halaman Period Management dengan periode aktif, status global, project, dan support, serta riwayat periode](images/guide/period-management.png)

*Tangkapan layar dengan data contoh.*

1. Buka `RPMO` › `Period Management`.
2. Baca kartu `Active Period`. Kartu ini menampilkan rentang tanggal dan status `Global (RPMO)`, `Project Domain`, dan `Support Domain`.
3. Baca `Period History` di bawahnya. Tiap baris menampilkan periode, rentang tanggal, dan ketiga status. Menu di kolom `Actions` menawarkan aksi yang boleh dipakai role Anda.

### Membuat dan membuka periode (Delivery RPMO Head)

1. Pilih `New Period`. Formulirnya berjudul `Create New Period`.
2. Pilih `Year` dan `Month`, lalu periksa `Start Date` dan `End Date`.
3. Konfirmasi formulir. Periode baru muncul sebagai periode tertunda dengan status `Not Open`.
4. Pilih `Open Period Globally`. Statusnya berubah menjadi `Open`.

### Membuka dan menutup bagian tim Anda (Project Head dan Support Head)

1. Temukan tim Anda pada kartu `Active Period`.
2. Pilih `Open Project Period` atau `Open Support Period` agar tim Anda dapat mencatat timesheet.
3. Setelah tim selesai, pilih `Close Project Period` atau `Close Support Period` lalu konfirmasi.

### Menutup periode untuk semua (Delivery RPMO Head)

1. Pastikan bagian Project dan Support sudah ditutup. Halaman mengingatkan bahwa keduanya harus ditutup terlebih dahulu.
2. Pilih `Close Period Globally`. Tombol ini tetap abu-abu sampai kedua bagian ditutup.
3. Konfirmasi.

### Menutup paksa bagian sebuah tim (kasus mendesak)

1. Pilih `Force Close Project` atau `Force Close Support`.
2. Baca peringatan pada dialog `Force Close Domain` lalu konfirmasi.

> [!WARNING]
> Tutup paksa tidak dapat dibatalkan, dan menghapus periode dengan `Delete Period` menghilangkannya secara permanen. Pakai keduanya hanya bila Anda yakin.

### Memutuskan permintaan akses terlambat

![Dialog Late Access Requests dengan permintaan yang menunggu persetujuan RPMO, batas akses, dan catatan RPMO](images/guide/period-management-requests.png)

*Tangkapan layar dengan data contoh.*

1. Pilih `Exception Requests` pada kartu `Active Period`.
2. Baca tiap permintaan: karyawan, periode, tim, alasan, dan statusnya, misalnya `Waiting for RPMO Approval`.
3. Bila Head sudah memutuskan, dialog menampilkan siapa yang menyetujui dan kapan.
4. Sebagai RPMO, isi `Access Deadline` dan `RPMO Notes`, lalu pilih `Approve & Set Deadline`. Keduanya wajib saat menyetujui. Pilih `Reject` untuk menolak.

### Memeriksa apa yang terjadi

Pilih `Audit Log` untuk melihat tiap aksi dengan kolom `Action`, `Actor`, dan `Timestamp`.

### Mengubah tanggal atau menghapus periode

Di `Period History`, buka menu pada kolom `Actions` sebuah baris lalu pilih `Edit Period Dates` atau `Delete Period`. Pilihan ini hanya tampil bila role Anda mengizinkan.

## Apa yang terjadi selanjutnya

Karyawan diberi tahu keputusan Anda. Setelah Head dan RPMO sama-sama menyetujui, karyawan dapat mengisi periode yang diminta sampai batas akses yang Anda tetapkan. Periode yang sudah ditutup tidak menerima timesheet baru tanpa permintaan akses terlambat yang disetujui.

## Pertanyaan umum

**Saya tidak melihat `New Period`.** Membuat periode membutuhkan izin `Manage Global`. Tanyakan kepada administrator.

**`Close Period Globally` berwarna abu-abu.** Bagian Project dan Support harus ditutup lebih dulu.

**Seseorang tidak dapat mengirim timesheet periode yang sudah ditutup.** Bila kondisinya khusus, ia mengirim `Request Late Access` dari `Calendar` › `Timesheets`, lalu disetujui Head-nya dan kemudian RPMO. Alurnya lengkap di [[late-access-request]].

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, app/Services/PeriodService.php, resources/views/rpmo/periods/index.blade.php -->

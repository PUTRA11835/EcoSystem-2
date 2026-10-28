---
key: guide-helpdesk
chapter: reference
order: 86
title: Panduan Delivery Support Service Helpdesk
summary: Rutinitas helpdesk: validasi tiket masuk, arahkan tiket, tinjau mandays customer, rapikan daftar tiket, dan siapkan laporan shift serta mingguan.
requires: ["@role:Delivery Support Service Helpdesk"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Support Service Helpdesk. Anda adalah pintu depan permintaan customer: Anda memeriksa yang masuk, mengarahkannya ke orang yang tepat, dan menjaga daftar tiket tetap rapi.

## Siapa yang menggunakan

Staf Helpdesk. Menu Anda mencakup `Ticket`, `Ticket Validation`, `Consultant Workload`, `Delivery` › `Support`, `SLA` › `SLA Report`, `Management` › `Hidden Tickets` dan beberapa halaman `Reporting` (`MD Validation`, `Ticketing Overview`, `Ticket by Modul`, `Log Shifting` dan `Weekly Consolidation`).

## Sebelum Anda mulai

- Tiket dapat memuat informasi kredensial customer. Pakai hanya untuk pekerjaan dan jangan pernah menyalinnya ke chat, catatan, email, atau dokumen lain.
- Keputusan Anda menentukan siapa yang mengerjakan permintaan customer dan apa yang disampaikan kepadanya. Baca sebelum menyetujui, dan tulis alasan yang jelas saat menolak.

## Langkah demi langkah

### Validasi tiket masuk

Permintaan baru dari customer menunggu di `Ticket Validation` sampai Anda memutuskan. Angka di samping menu menunjukkan berapa yang menunggu.

1. Buka `Ticket Validation`. Daftar `Pending Validation` menampilkan permintaan dengan pengirim, subjek, prioritas, kanal, dan tanggal kirim. Pakai `View Rejected` untuk melihat yang sudah ditolak.
2. Pilih sebuah permintaan untuk membacanya. Bagian atas menampilkan `From`, `Date` dan `Subject`, dan `AI Ticket Analyzer` dapat menyarankan klasifikasi. Periksa sarannya sebelum Anda mengandalkannya.

![Jendela Incoming Ticket Details dengan kolom klasifikasi serta tombol Approve dan Reject](images/guide/ticket-validation.png)

*Tangkapan layar dengan data contoh.*

3. Isi `Ticket Classification`: `Type` dan `Priority` wajib, `Scale` opsional. Pilih `Delivery Support` tempat tiket itu berada. Tambahkan `Name`, `No HP` dan `Module(s)` bila belum ada.
4. Pilih `Approve` untuk menjadikannya tiket sungguhan, atau `Reject` dan tulis alasan untuk pengirim.

### Arahkan dan ikuti tiket

1. Buka `Ticket`, pilih tiketnya, lalu tetapkan lead atau anggota tim. Lihat [[ticket-assignment]].
2. Buka `Consultant Workload` lebih dulu untuk melihat siapa yang masih punya ruang.
3. Saat konsultan mengusulkan Customer Mandays, tinjau lalu kirim ke customer. Lihat [[ticket-mandays]].

### Jaga daftar tiket tetap rapi

1. Untuk menyembunyikan tiket dari daftar utama, pakai tombol sembunyikan pada tiket lalu konfirmasi. Tiket tidak dihapus.
2. Buka `Management` › `Hidden Tickets` untuk melihat tiket yang disembunyikan. Pilih `Tampilkan` pada sebuah baris untuk memunculkannya kembali di daftar utama.

### Siapkan laporan

1. `Log Shifting`: pilih `Dari Tanggal` dan `Sampai Tanggal` (dan jam bila perlu), atau rentang cepat seperti `Hari Ini` atau `7 Hari Terakhir`. Pilih `Tampilkan` untuk memuat catatan SLA, lalu `Export Excel`.
2. `Weekly Consolidation`: simpan rekap mingguan tiket terbuka per modul. Lihat [[weekly-consolidation]].
3. `Ticketing Overview`, `Ticket by Modul` dan `MD Validation` menampilkan angka tiket dan mandays. `SLA` › `SLA Report` menampilkan waktu respons dan penyelesaian.

## Apa yang terjadi selanjutnya

Permintaan yang disetujui menjadi tiket dan lead serta tim diberi tahu. Permintaan yang ditolak kembali kepada pengirim beserta alasan Anda.

## Pertanyaan umum

**Angka di `Ticket Validation` tidak turun.** Angka itu menghitung permintaan yang masih `Pending Validation`. Setujui atau tolak permintaan tersebut.

**Saya tidak menemukan tiket di daftar utama.** Mungkin tiket itu disembunyikan. Periksa `Hidden Tickets`.

**Tabel `Log Shifting` kosong.** Pilih rentang tanggal dan pilih `Tampilkan` lebih dulu.

## Topik terkait

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[weekly-consolidation]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/staging, resources/views/ticket/index.blade.php, resources/views/management/hidden-tickets/index.blade.php, resources/views/reporting/log-shifting.blade.php -->

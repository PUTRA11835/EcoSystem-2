---
key: guide-sales
chapter: reference
order: 89
title: Panduan role Sales dan Marketing
summary: Cara staf sales dan marketing mengikuti proyek, customer, dan outlook penagihan, serta apa yang hanya dapat dibaca.
requires: ["@role:Sales Operation Account Executive", "@role:Sales Operation Director", "@role:Sales Operation User", "@role:Sales Marketing User", "@role:Sales Marketing Manager"]
updated: 2026-10-28
---
Panduan ini untuk role Sales Operation dan Sales Marketing. Anda mengikuti proyek customer Anda, memeriksa data customer, dan memantau penagihan.

## Siapa yang menggunakan

Sales Operation Account Executive, Sales Operation Director, Sales Operation User, Sales Marketing User dan Sales Marketing Manager. Menu Anda mencakup `Home`, `Calendar` › `Events`, `Master` › `Business Partner` dan `Delivery` › `Project`. Sebagian besar role ini juga punya `Reporting` › `Collection Outlook` dan `Collection Outlook (Support)`, dan Sales Operation User juga punya `Business Dev`. Menu yang tidak Anda lihat bukan bagian dari role Anda.

## Sebelum Anda mulai

- Data proyek dan customer memuat harga, kontak, dan informasi kredensial. Pakai hanya untuk pekerjaan Anda, dan jangan pernah menyalinnya ke chat, email, atau dokumen lain.
- Sebagian besar data proyek hanya dapat dibaca oleh sales. Jika kolom atau tombol tidak ada, itu bukan bagian dari role Anda.

## Langkah demi langkah

### Ikuti proyek Anda

1. Buka `Delivery` › `Project`. Kartu di atas menghitung proyek menurut status dan jadwal, dan tabel mencantumkannya. Klik sebuah kartu untuk menyaring daftar, atau pilih `Export Excel` untuk mengunduhnya.
2. Pilih sebuah proyek untuk membukanya. Tab-tabnya adalah `General`, `Delivery Info`, `Team`, `Documents`, `Issues`, `Risks`, `Location`, `Planning` dan `Plan Cost`.

![Halaman detail proyek dengan General Information bertanda Read only](images/guide/project-detail-readonly.png)

*Tangkapan layar dengan data contoh.*

3. `General` hanya dapat dibaca. Tab lain dapat diubah menurut role Anda: misalnya `Delivery Info` dan `Documents` untuk sebagian besar role sales, dan `Location` untuk Account Executive. Tab yang tidak dapat Anda ubah tidak punya tombol ubah.
4. Pakai `Create Folder` untuk membuat folder OneDrive proyek sebelum Anda mengunggah dokumen.

### Periksa penagihan: Collection Outlook

![Laporan Collection Outlook dengan kartu ringkasan dan tabel bulanan](images/guide/collection-outlook.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Collection Outlook` untuk proyek, atau `Collection Outlook (Support)` untuk item support.
2. Isi `From` dan `To` (bulan dan tahun), pilih `Account Executive` atau `All AE`, lalu pilih `Apply`.
3. Baca kartunya: `Total Outlook`, `Paid`, `Invoiced`, `Not Invoiced` dan `Payment Terms`.
4. Di tabel, setiap termin pembayaran berada di bulan tanggal perkiraannya. Klik sebuah jumlah untuk melihat termin dan rincian invoice. Hijau berarti sudah dibayar, biru berarti sudah di-invoice dan menunggu pembayaran, dan merah berarti belum di-invoice.
5. Pilih `Export Excel` untuk mengunduh tampilan ini.

### Cari customer

1. Buka `Master` › `Business Partner`.
2. Pakai `Status`, `Type`, `Business Partner` (cari menurut email atau nama perusahaan) dan `Customer Group`, lalu `Apply`. `Reset` menghapusnya, dan `View Grouping` menampilkan customer menurut group.
3. Kolom `Actions` berisi tombol untuk membuka sebuah partner. Bila kolom itu kosong bagi Anda, role Anda dapat membaca daftar tetapi tidak dapat membuka partner. Tanyakan kepada administrator bila Anda membutuhkannya.

### Rencanakan waktu Anda

Pakai `Calendar` › `Events` untuk rapat dan pengingat. Lihat [[calendar-timesheet]].

## Apa yang terjadi selanjutnya

Perubahan yang Anda simpan pada tab yang dapat diubah muncul bagi semua orang yang dapat membuka proyek. Angka `Collection Outlook` mengikuti termin pembayaran yang disimpan di setiap proyek atau item support.

## Pertanyaan umum

**Saya tidak dapat menambah proyek atau customer.** Pembuatannya untuk administrator dan project administrator.

**Sebuah termin pembayaran tidak ada di `Collection Outlook`.** Termin perlu tanggal perkiraan, tanggal bayar, atau tanggal invoice agar muncul di sebuah bulan. Minta pemilik proyek mengisinya.

**`Collection Outlook` tidak ada di menu saya.** Itu bukan bagian dari role Anda.

## Topik terkait

- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/delivery/project, resources/views/reporting/collection-outlook.blade.php, resources/views/master -->

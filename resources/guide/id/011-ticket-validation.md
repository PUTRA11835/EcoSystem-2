---
key: ticket-validation
chapter: work-service
order: 11
title: Ticket Validation
summary: Tinjau permintaan customer yang baru, periksa analisis AI, klasifikasikan, lalu setujui menjadi tiket atau tolak dengan alasan.
requires: [tickets.staging]
updated: 2026-10-28
---
Ticket Validation adalah pintu depan permintaan customer. Permintaan yang masuk lewat email atau portal customer menunggu di sini sampai seseorang memutuskan apakah ia menjadi tiket sungguhan.

## Siapa yang menggunakan

Administrator dan role yang meninjau permintaan masuk, seperti Helpdesk, Delivery Support Head, Delivery RPMO Head dan Delivery Support Manager. Anda hanya melihat halaman ini bila role Anda punya menu `Ticket Validation`. Angka di samping menu menunjukkan berapa permintaan yang menunggu.

## Sebelum Anda mulai

- Sebuah permintaan dapat memuat informasi kredensial customer. Pakai hanya untuk pekerjaan dan jangan pernah menyalinnya ke chat, catatan, email, atau dokumen lain.
- Menyetujui membuat tiket dan memberi tahu orang-orang. Menolak memberi tahu customer. Baca permintaannya sebelum memutuskan.
- Menyetujui membutuhkan analisis AI yang sudah selesai. Ini aturan keras, jadi sediakan waktu sejenak untuk itu pada setiap permintaan.

## Langkah demi langkah

### Cari permintaan

![Daftar Ticket Validation dengan kartu status dan satu permintaan pending](images/guide/ticket-validation-list.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Ticket Validation`. Kartu menampilkan `Pending`, `Approved` dan `Rejected`.
2. Daftar menampilkan pengirim, subjek, `Priority`, `Channel`, `Status` dan `Submit Date`. Pakai filter status (`Pending Validation`, `All Status`, `Approved`, `Rejected`) dan `Refresh` untuk memperbarui daftar.
3. Pilih sebuah permintaan untuk membuka `Incoming Ticket Details`.

### Baca dan periksa analisis AI

![Jendela Incoming Ticket Details dengan kolom klasifikasi serta tombol Approve dan Reject](images/guide/ticket-validation.png)

*Tangkapan layar dengan data contoh.*

1. Di bagian atas Anda melihat `From`, `Date`, `Subject`, `Description` dan `Attachments` bila ada.
2. `AI Ticket Analyzer` berjalan saat Anda membuka permintaan yang belum dianalisis, dan menyiapkan satu analisis per permintaan. Kolom klasifikasi yang masih kosong diisi dari hasilnya. Analisis menyarankan modul dan siapa yang dapat mengambilnya: `Module Lead` atau konsultan bersertifikat untuk modul itu, serta `Lead` berdasarkan konsultan sebelumnya milik customer tersebut. Langkah untuk respons pertama juga disertakan. Anggap itu saran.
3. Pakai `Ask a question` di bawah analisis untuk bertanya lebih lanjut, misalnya apa langkah pertamanya.
4. Bila analisis gagal, pilih `Re-analyze`. Anda juga dapat mengisi klasifikasi sendiri, tetapi Anda tidak dapat menyetujui sampai ada analisis yang selesai.
5. Bila jendela menulis bahwa analisis disembunyikan dari Anda, analisis itu merujuk pada catatan kredensial tersimpan milik customer. Anda butuh akses lihat `Customer Credential` untuk melihatnya. Tanyakan kepada administrator.

### Klasifikasikan dan setujui

1. Di `Ticket Classification` pilih `Type` dan `Priority`. `Scale` opsional.
2. Pilih `Delivery Support` tempat tiket itu berada. Wajib bila customer punya item delivery support, dan harus milik customer itu.
3. Bila permintaan masuk lewat domain email milik customer induk, kolom `For customer` memungkinkan Anda memilih customer akhir yang sebenarnya.
4. Di `Additional Info` periksa `Name`, `No HP`, `Module(s)` dan `Client`, dan perbaiki bila perlu.
5. Pilih `Approve`. Permintaan menjadi tiket, customer dan tim diberi tahu, dan chat riset AI bersama untuk tiket itu disiapkan dari hasil analisis.

### Tolak permintaan

1. Pilih `Reject`.
2. Tulis `Reason for Rejection` (paling banyak 1000 karakter). Tulis dengan jelas, karena customer diberi tahu.
3. Konfirmasi. Permintaan pindah ke daftar yang ditolak.

### Lihat permintaan yang ditolak

![Daftar Rejected Tickets dengan alasan penolakan](images/guide/ticket-validation-rejected.png)

*Tangkapan layar dengan data contoh.*

Pilih `View Rejected` (lencana menunjukkan jumlahnya). Anda dapat mencari menurut customer atau deskripsi, membaca `Rejection Reason`, dan membuka `Detail`. Pakai `Back to Ticket Validation` untuk kembali.

## Apa yang terjadi selanjutnya

Permintaan yang disetujui muncul di `Ticket` bersama lead dan timnya, dan customer diberi tahu. Permintaan yang ditolak tetap ada di daftar tolak beserta alasan Anda, dan customer menerima pemberitahuan yang memuat alasan itu.

## Pertanyaan umum

**`Approve` menulis analisis AI harus selesai dulu.** Tunggu analisis selesai, atau pilih `Re-analyze` bila gagal. Tidak ada jalan pintas untuk langkah ini.

**Saya tidak dapat memilih Delivery Support.** Customer tidak punya yang terdaftar sehingga kolomnya opsional, atau Anda memilih yang milik customer lain.

**Daftar kosong tetapi angka di menu tidak.** Pilih `Refresh` dan periksa filter statusnya.

**Menu membuka halaman bertuliskan `Forbidden`.** Role Anda punya menunya, tetapi tidak punya izin untuk menangani permintaan masuk. Tanyakan kepada administrator.

**Saya tidak sengaja menolak sebuah permintaan.** Permintaan yang sudah punya keputusan tidak dapat diproses lagi. Minta customer mengirimnya lagi, atau buat tiketnya dari halaman `Ticket` bila role Anda mengizinkan.

<!-- sources: routes/web.php, app/Http/Controllers/StagingTicketController.php, resources/views/staging/index.blade.php, resources/views/staging/rejected.blade.php -->

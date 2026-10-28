---
key: ticket-validation
chapter: work-service
order: 20
title: Ticket Validation
summary: Periksa Ticket kiriman pelanggan lalu setujui menjadi Ticket resmi, atau tolak disertai alasan.
requires: [tickets.staging]
updated: 2026-10-27
---
Ticket yang dikirim pelanggan lewat email atau portal pelanggan menunggu di halaman `Ticket Validation` (berjudul `Incoming Ticket Validation`). Anda memeriksa tiap Ticket, mengklasifikasikannya, lalu menyetujuinya menjadi Ticket resmi atau menolaknya.

## Siapa yang menggunakan

Pengguna Delivery Support, Helpdesk, kepala bagian, dan administrator yang berwenang melakukan validasi. Tombol `Approve` dan `Reject` hanya tampil bila role Anda memiliki izinnya.

## Sebelum Anda mulai

- Lencana angka di samping `Ticket Validation` pada sidebar menunjukkan berapa Ticket yang menunggu.
- Ketahui tipe Ticket, prioritas, dan, bila pelanggan memilikinya, delivery support yang akan menanganinya.

## Langkah demi langkah

### Memeriksa Ticket masuk

1. Buka `Work & Service` › `Ticket Validation`.
2. Periksa tiga penghitung: `Pending`, `Approved`, dan `Rejected`.
3. Gunakan filter status di bagian atas. Awalnya `Pending Validation`; pilih `All Status`, `Approved`, atau `Rejected` untuk melihat yang lain.
4. Klik `Refresh` untuk memuat ulang. Daftar juga menyegarkan diri setiap 30 detik, dan halaman memeriksa email baru setiap menit.
5. Klik `Validate` pada baris yang masih pending. Untuk baris yang sudah disetujui atau ditolak, tombolnya bertuliskan `Detail`.

Daftar menampilkan `Customer / Sender`, `Description / Subject`, `Priority`, `Channel` (`Email` atau `Web`), `Status`, dan `Submit Date`.

### Menyetujui Ticket

1. Di jendela rincian, baca pesan dan lampirannya.
2. Bila perlu, baca panel `AI Ticket Analyzer`. Panel ini memberi gambaran, langkah penyelesaian, dan saran penanggung jawab. Klik `Apply suggestion` untuk menyalin sarannya ke formulir.
3. Pada `Ticket Classification`, pilih `Type` dan `Priority`. Keduanya wajib.
4. Pilih `Scale` bila diperlukan.
5. Pilih `Delivery Support`. Kolom ini wajib bila pelanggan memiliki delivery support terdaftar, dan hanya dapat memilih yang milik pelanggan tersebut.
6. Jika email ditujukan ke pelanggan induk, pilih pelanggan yang tepat pada `For customer`.
7. Isi `Additional Info` (`Name`, `No HP`, `Module`, `Client`) bila berguna.
8. Klik `Approve`.

### Menolak Ticket

1. Klik `Reject` di jendela rincian.
2. Isi `Reason for Rejection`. Kolom ini wajib.
3. Klik `Confirm Rejection`.

Untuk meninjau Ticket yang ditolak, klik `View Rejected` di halaman ini. Halaman tersebut berjudul `Rejected Tickets`; gunakan kotak pencariannya dan `Back to Ticket Validation` untuk kembali.

## Apa yang terjadi selanjutnya

- Menyetujui membuat Ticket di [[tickets]] dengan status `Open`. Baris yang disetujui lalu menampilkan nomor Ticket baru, dan pelanggan menerima pemberitahuan bahwa Ticket telah divalidasi.
- Menolak menandai baris sebagai `Rejected` dan memberi tahu pelanggan beserta alasan Anda.
- Waktu respons pada [[sla]] dihitung sejak Ticket diterima sampai divalidasi.

## Pertanyaan umum

**Tombol Approve tidak ada.** Role Anda mungkin hanya dapat melihat halaman ini. Hubungi administrator untuk meminta akses.

**Muncul pesan delivery support wajib diisi.** Pelanggan memiliki delivery support terdaftar, jadi pilih salah satu dari daftar.

## Topik terkait

- [[tickets]]
- [[sla]]
- [[report-ticketing]]

<!-- sources: routes/web.php, app/Http/Controllers/StagingTicketController.php, resources/views/staging/index.blade.php, resources/views/staging/rejected.blade.php, app/Services/SlaService.php -->

---
key: tickets
chapter: work-service
order: 10
title: Ticket
summary: Cari, buat, dan kerjakan Ticket dukungan, dari daftar hingga halaman Ticket.
requires: [tickets.inbox]
updated: 2026-10-27
---
Halaman `Ticket` (berjudul `Support Tickets`) menampilkan semua permintaan dukungan yang boleh Anda lihat. Buka sebuah Ticket untuk membalas, menambah catatan, mengubah propertinya, dan memantau progresnya.

## Siapa yang menggunakan

Pengguna Delivery Support, Helpdesk, manajer dan kepala bagian, serta administrator. Tab, tombol, dan panel yang tampil bergantung pada akses Anda. Jika ada yang tidak muncul, minta administrator memberi akses.

## Sebelum Anda mulai

- Ticket yang dikirim pelanggan lewat email atau portal pelanggan belum muncul di sini sampai seseorang menyetujuinya di [[ticket-validation]].
- Untuk membuat Ticket sendiri, Anda memerlukan tombol `Create Ticket`. Tombol ini hanya tampil bila role Anda memiliki izinnya.

## Langkah demi langkah

### Mencari Ticket

1. Buka `Work & Service` › `Ticket`.
2. Pilih tab bila Anda memiliki lebih dari satu: `My Tickets`, `All Tickets`, `Unassigned` (pada sebagian role bernama `Unassigned Ticket`), atau `Ticket Modul`. `Ticket Modul` hanya tampil bila Anda module lead.
3. Pilih kartu status di bagian atas, seperti `Open`, `In Progress`, `Wait Customer`, `Wait 3rd Party`, `Wait Confirm`, `Hold`, `Cancelled`, atau `Closed`, untuk menyaring daftar. `Total` menampilkan semuanya kembali.
4. Gunakan tombol filter pada judul kolom untuk mencari nomor Ticket (`Tiket`), `Description`, rentang tanggal (`Start Date`), atau untuk mengurutkan.
5. Gunakan `Prev` dan `Next` di bawah kartu untuk berpindah halaman.

### Membuat Ticket

1. Klik `Create Ticket`.
2. Pilih `Customer`.
3. Isi `Subject / Description`.
4. Pilih `Priority` (`Very High`, `High`, `Medium`, atau `Low`) dan `Ticket Type` (`Incident`, `Change Request`, `Service Request`, `EWA`, `RISE`, `Consult`, atau `Internal`).
5. Bila perlu, pilih `Scale` (`Simple`, `Medium`, atau `Complex`) yang dipakai untuk [[sla]]. Isi juga `To`, `CC`, `Additional Info`, pesan, dan lampiran (maksimal 20 MB per berkas).
6. Klik `Create Ticket` di bagian bawah formulir.

> [!NOTE]
> Ticket bertipe `Internal` tidak ditampilkan kepada pelanggan.

### Mengerjakan Ticket

1. Klik sebuah baris untuk membuka halaman Ticket.
2. Tulis di kotak balasan. Pilih `Internal Note` untuk catatan yang hanya dilihat tim Anda, atau `Send Reply` / `Send via Email` untuk menjawab pelanggan.
3. Buka `Properties` di sebelah kanan untuk mengubah `Status`, `Priority`, `Scale`, `Ticket Type`, dan `PIC (In Charge)`, lalu klik `Save All`. Kolom hanya dapat diubah bila role Anda mengizinkan.
4. Gunakan `Team Members` untuk menambahkan rekan ke Ticket.
5. Jika Ticket belum punya penanggung jawab, klik `Take This Ticket`, atau gunakan `Assign Ticket Lead` untuk menyerahkannya kepada orang lain.
6. Gunakan `Meeting` untuk menjadwalkan rapat, dan `Deliverable` › `View Documents` untuk melihat dokumen pada Ticket.

### Mengusulkan mandays

Di bagian `Mandays` Anda dapat mengusulkan `Customer Mandays` dan `Resolution Days`. Usulan melewati label `Draft`, `Pending Review` (atau `Pending Head`), `Approved`, dan untuk resolution days `Rejected`. Peninjau memakai `Review Mandays Proposal` atau `Review Resolution Days`. Tombol ini hanya tampil untuk role yang mengusulkan atau meninjau.

## Apa yang terjadi selanjutnya

- Perubahan status memperbarui kartu dan hitungan `Day on Close` di daftar.
- Untuk Ticket `Incident`, `Change Request`, dan `Consult`, jam [[sla]] berhenti sementara saat Ticket menunggu pelanggan, pihak ketiga, atau konfirmasi, atau berstatus `Hold`.
- Pengguna dengan akses yang sesuai dapat mengklik `Export` untuk mengunduh daftar yang sudah disaring dalam bentuk Excel.

## Pertanyaan umum

**Saya tidak melihat Ticket yang saya harapkan.** Periksa tab dan kartu status yang aktif, lalu hapus filter kolom. Apa yang dapat Anda lihat bergantung pada role Anda.

**Saya tidak melihat `Create Ticket`.** Role Anda belum mencakupnya. Hubungi administrator.

## Topik terkait

- [[ticket-validation]]
- [[my-tasks-workload]]
- [[sla]]
- [[report-ticketing]]

<!-- sources: routes/web.php, app/Http/Controllers/TicketViewController.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/partials/sidebar.blade.php -->

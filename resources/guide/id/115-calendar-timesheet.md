---
key: calendar-timesheet
chapter: my-workspace
order: 115
title: Calendar dan Timesheets
summary: Lihat event di kalender, catat jam kerja pada sebuah ticket, kirim untuk persetujuan, dan minta akses terlambat.
requires: [calendar.events, calendar.timesheets]
updated: 2026-10-28
---
`Calendar` menampilkan event yang boleh Anda lihat. `Timesheets` adalah tempat Anda mencatat jam kerja dan mengirimnya untuk persetujuan.

## Siapa yang menggunakan

Employee yang memiliki `Events` atau `Timesheets` di bawah `Calendar`. Head dan approver memakai halaman timesheet yang sama untuk memeriksa timnya.

## Sebelum Anda mulai

- Ketahui ticket yang Anda kerjakan, tanggalnya, jam mulai dan selesai, serta apa yang Anda lakukan.
- Tombol hanya tampil bila role Anda mengizinkan. Jika `Create Event` atau `Create Timesheet` tidak ada, hubungi administrator.

## Langkah demi langkah

### Membaca kalender

![Halaman Events Calendar bulan Oktober dengan Today, navigasi bulan, dan tampilan Month, Week, dan Day](images/guide/calendar-events.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Calendar` › `Events`. Halamannya berjudul `Events Calendar`.
2. Gunakan tombol panah untuk berpindah bulan, atau pilih `Today` untuk kembali ke tanggal sekarang.
3. Pindah antara tampilan `Month`, `Week`, dan `Day`.
4. Pilih sebuah event untuk membaca rinciannya.

### Membuat event

![Formulir Create New Event dengan judul, jenis, deskripsi, tanggal, jam, dan lokasi](images/guide/calendar-create-event.png)

*Tangkapan layar dengan data contoh.*

1. Pilih `Create Event`. Formulirnya berjudul `Create New Event`.
2. Isi `Event Title` dan pilih `Event Type`: `Meeting`, `Task`, `Deadline`, `Urgent`, atau `Reminder`.
3. Isi `Start Date` dan `Start Time`. Tambahkan `End Date` dan `End Time` bila perlu, atau centang `All day event`.
4. Tambahkan `Description` dan `Location` bila berguna.
5. Pilih `Save Event`.

### Mencatat jam kerja

![Halaman Timesheets dengan periode, kartu ringkasan, tombol Create Timesheet, dan daftar entri beserta statusnya](images/guide/timesheets.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Calendar` › `Timesheets`. Label di samping tombol menunjukkan periode pelaporan, misalnya `October 2026`, serta apakah statusnya `Open`.
2. Pilih `Create Timesheet`. Formulirnya berjudul `Log Working Hours`.

![Formulir Log Working Hours dengan tipe, tanggal, jam, ticket, MD consumed, activity, dan notes](images/guide/timesheets-log-modal.png)

*Tangkapan layar dengan data contoh.*

3. Pilih `Type`, lalu periksa `Submit Date`. `Reporting Period` terisi otomatis.
4. Pilih `Time` mulai dan selesai. Formulir menampilkan `Duration`. Jam selesai harus lebih besar dari jam mulai.
5. Pilih `Ticket`. Kotak `Customer`, `Quota MD`, dan `Remaining` terisi dari ticket itu.
6. Atur `Activity Date`, yaitu kapan pekerjaan itu benar-benar terjadi.
7. Isi `MD Consumed` dan centang `On Site` bila Anda bekerja di lokasi customer.
8. Jelaskan pekerjaan di `Activity`. `Notes` bersifat opsional.
9. Pilih `Save Timesheet`. Entri dimulai sebagai `Draft`, dan masih dapat Anda ubah atau hapus.

### Mengirim untuk persetujuan

1. Pilih entri yang masih draft di daftar.
2. Pilih `Submit Timesheets` lalu konfirmasi.
3. Status berubah menjadi `Submitted`. Setelah itu Anda tidak dapat mengubahnya sendiri.

Kartu di atas daftar menghitung entri Anda sebagai `Draft`, `Submitted`, `Approved`, dan `Rejected`, serta menampilkan `Total Quota MD` dan `Total MD Consumed`.

### Meminta akses terlambat

1. Jika sebuah periode sudah ditutup, pilih `Request Late Access`.
2. Pilih periodenya dan tulis alasannya.
3. Kirim permintaan. Permintaan diperiksa oleh Head Anda lebih dulu, lalu oleh RPMO.

> [!NOTE]
> Jika Anda berwenang menyetujui timesheet, Anda juga melihat `Approve` dan `Reject`, termasuk untuk beberapa entri sekaligus. Selalu tulis alasan yang jelas saat menolak.

## Apa yang terjadi selanjutnya

Entri yang ditolak menampilkan alasannya: perbaiki lalu kirim ulang. Permintaan akses terlambat menampilkan `Pending Head`, lalu `Pending RPMO`. Setelah keduanya menyetujui, Anda dapat mengisi periode itu sampai batas waktunya.

## Pertanyaan umum

**Saya tidak dapat membuat timesheet untuk bulan lalu.** Periodenya kemungkinan sudah ditutup. Gunakan `Request Late Access`.

**Bisakah saya mengubah timesheet yang sudah dikirim?** Tidak. Minta orang yang menyetujuinya untuk menolak, lalu perbaiki.

**Mengapa `Quota MD` dan `Remaining` kosong?** Keduanya terisi setelah Anda memilih ticket.

<!-- sources: routes/web.php, app/Http/Controllers/CalendarController.php, resources/views/calendar/events.blade.php, resources/views/calendar/timesheets.blade.php -->

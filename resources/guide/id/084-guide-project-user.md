---
key: guide-project-user
chapter: reference
order: 84
title: Panduan Delivery Project User
summary: Rutinitas konsultan proyek: ikuti tiket Anda, perbarui progres, catat waktu, dan periksa mandays.
requires: ["@role:Delivery Project User"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Project User, yaitu konsultan yang mengerjakan proyek dan tiket yang terkait dengannya. Isinya rutinitas harian yang sederhana dan petunjuk ke topik yang lebih rinci.

## Siapa yang menggunakan

Konsultan dengan role Delivery Project User. Menu Anda adalah `Home`, `AI Assistant`, `AI Research`, `Ticket`, `My Tasks`, `Calendar` (`Events` dan `Timesheets`) dan `Reporting` › `MD Validation`. Menu yang tidak Anda lihat bukan bagian dari role Anda.

## Sebelum Anda mulai

- Jika tombol di bawah tidak ada, itu bukan bagian dari role Anda. Tanyakan kepada Project Head atau Helpdesk.
- Tiket dapat menampilkan informasi kredensial customer. Pakai hanya untuk pekerjaan, dan jangan pernah menyalinnya ke chat, catatan, email, atau dokumen lain.
- Jangan memasukkan kredensial atau data pribadi customer ke alat AI.

## Langkah demi langkah

### Setiap hari

1. Buka `My Tasks`. Kartu di bagian atas menampilkan `Active Tickets`, `Alloc MD`, `Remaining`, `Workload` dan `Load Score` Anda. Pakai kolom bulan, tahun, dan pencarian untuk menemukan tiket.

![Halaman My Tasks dengan kartu ringkasan dan tiket yang Anda pimpin](images/guide/my-tasks.png)

*Tangkapan layar dengan data contoh.*

2. Buka `Ticket` untuk melihat semua tiket yang boleh Anda lihat. Kartu di atas menghitungnya menurut status, dan `Reset Filter` menghapus filter Anda.
3. Buka tiket untuk membalas customer atau menulis `Internal Note`. Catatan internal hanya dilihat tim Anda, sedangkan balasan dilihat customer, jadi pastikan Anda memakai yang tepat sebelum mengirim.
4. Jaga status sesuai keadaan sebenarnya, misalnya `Wait Customer` saat menunggu jawaban. Status yang benar menjaga SLA tetap adil.
5. Catat jam kerja Anda di `Calendar` › `Timesheets` sebelum hari berakhir. Lihat [[calendar-timesheet]].

### Periksa mandays Anda

1. Buka `Reporting` › `MD Validation`.
2. Pilih bulan. Kartu menampilkan `Total Entries`, `Match` (terpakai sama dengan kuota), `Less` (terpakai di bawah kuota) dan `Over` (terpakai di atas kuota).
3. Baca tabel menurut karyawan, tanggal, tiket, dan customer untuk membandingkan `MD Input`, `Quota MD` dan `Remain`.
4. Pilih `Export MD` bila Anda membutuhkan datanya di spreadsheet.

### Rencanakan waktu Anda

Pakai `Calendar` › `Events` untuk rapat, tenggat, dan tugas. Lihat [[calendar-timesheet]].

## Apa yang terjadi selanjutnya

Timesheet yang Anda kirim diteruskan ke atasan untuk disetujui. Waktu yang disetujui masuk ke laporan beban kerja dan mandays yang dibaca manajer Anda.

## Pertanyaan umum

**Tiket tidak muncul di `My Tasks`.** Hanya tiket yang Anda pimpin atau Anda ikuti sebagai anggota tim yang muncul. Minta ticket lead menambahkan Anda.

**Saya tidak dapat membuat tiket atau menugaskannya.** Tindakan itu milik Helpdesk dan para Head.

**`MD Validation` tidak menampilkan data.** Tidak ada entri yang disetujui pada bulan yang Anda pilih. Ganti bulannya.

## Topik terkait

- [[calendar-timesheet]]
- [[sign-in-and-security]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/task.blade.php, resources/views/reporting/reporting.blade.php, resources/views/calendar/timesheets.blade.php -->

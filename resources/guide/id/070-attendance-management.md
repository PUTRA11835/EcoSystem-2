---
key: attendance-management
chapter: human-capital
order: 70
title: Pengelolaan Attendance
summary: Tinjau Attendance harian dan bulanan, putuskan permintaan koreksi, serta atur shift dan aturan lokasi.
requires: [general.attendance, general.attendance.correction, general.settings.attendance, general.settings.shifts]
updated: 2026-10-27
---
`Attendance` memberi HR rekap Check-in dan Check-out semua orang, tempat memutuskan permintaan koreksi, serta pengaturan yang mengatur shift dan pemeriksaan lokasi.

## Siapa yang menggunakan

Staf HR yang memantau Attendance. Employee mencatat Attendance sendiri di My Attendance.

## Sebelum Anda mulai

Buka `Human Capital` › `Attendance`. Hub ini memiliki hingga lima tab: `Daily Recap`, `Monthly Recap`, `Corrections`, `Shifts`, dan `Settings`. Anda melihat tab yang diizinkan untuk Role Anda.

## Langkah demi langkah

### Membaca rekap harian

1. Buka `Daily Recap`.
2. Pilih `Date`, lalu `Department`, `Status`, dan kata `Search` bila perlu (nama, kode Employee, posisi, atau departemen). Pilih `Apply`.
3. Baca kartu ringkasan: `Recorded`, `Present`, `Late`, `Absent`, `Sick`, dan `Leave / Permit`.
4. Di tabel, periksa `Shift`, `Check-in`, `Check-out`, lokasi, `Late`, `Early Leave`, `Work`, dan `Overtime`. Baris bertanda `Corrected` memiliki koreksi yang sudah disetujui.
5. Pilih `Export Excel` untuk mengunduh data hari itu.

### Membaca rekap bulanan

1. Buka `Monthly Recap`.
2. Cari berdasarkan `Employee Name`, lalu pilih `Month` dan `Location`.
3. Baca satu baris per Employee dan satu kolom per hari. Arahkan kursor ke sebuah hari untuk melihat jam masuk dan keluar serta menit keterlambatan. Kartu ringkasan menampilkan `Total Employees`, `Work Days`, `Average Present`, dan `Complete Days`.

### Memutuskan permintaan koreksi

1. Buka `Corrections`. Kartu menampilkan `Pending Review`, `Approved`, dan `Rejected`.
2. Atur `Status` ke `Pending` untuk melihat permintaan yang menunggu Anda.
3. Bandingkan `Current Data` dengan `Proposed Correction`, dan baca `Reason`.
4. Ketik catatan HR pada kolom yang tersedia. Catatan bersifat opsional untuk menyetujui, tetapi wajib untuk menolak.
5. Pilih `Approve` atau `Reject`. Setelah disetujui, Attendance diperbarui dan jam sebelumnya tetap tersimpan sebagai catatan.

### Mengelola shift

1. Buka `Shifts` lalu pilih `Add Shift`.
2. Atur nama shift, jam kerja, toleransi keterlambatan, jam istirahat, dan hari kerja.
3. Gunakan aksi assign pada sebuah shift untuk memilih Employee yang memakainya.

Sebuah shift dapat menjadi shift bawaan, shift lintas hari (overnight), serta aktif atau nonaktif.

### Mengubah aturan Attendance

Di `Settings`, Anda dapat mengatur:

- `Geofence Mode`: `Off` (lokasi tidak diperiksa), `Flag` (Attendance di luar radius tetap dicatat dan ditandai untuk ditinjau HR, disarankan), atau `Enforce` (Attendance di luar radius ditolak), serta `Accuracy Threshold (meters)`.
- `Source & Default Hours`: jumlah maksimum shift aktif per Employee, Check-in dan Check-out bawaan, toleransi keterlambatan, dan jumlah jam setelah Check-out yang terlupa ditutup otomatis.
- `Corrections`: apakah Employee boleh mengajukan koreksi sendiri dan `Correction Window (days)`.
- `Attendance Sources`: sumber asal data Attendance.

> [!IMPORTANT]
> Check-in dan Check-out dicatat sekali sehari. Koreksi adalah satu-satunya cara mengubah jam.

## Apa yang terjadi selanjutnya

Koreksi yang disetujui memperbarui rekap. Employee melihat keputusan dan catatan Anda di My Attendance. Permintaan Overtime dibandingkan dengan catatan Attendance, lihat [[overtime-management]].

## Pertanyaan umum

**Mengapa seseorang tercatat Late?** Check-in-nya lebih lambat daripada jam mulai shift ditambah toleransi keterlambatan.

**Bisakah Employee mengajukan koreksi?** Hanya bila pengaturannya aktif; jika tidak, HR yang menyesuaikan jamnya.

## Topik terkait

- [[overtime-management]]
- [[leave-permit-management]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/attendance/daily.blade.php, resources/views/hr-general/attendance/monthly.blade.php, resources/views/hr-general/attendance/corrections.blade.php, resources/views/hr-general/attendance/settings.blade.php, resources/views/hr-general/attendance/shifts/index.blade.php, resources/views/hr-general/attendance/components/hub-tabs.blade.php -->

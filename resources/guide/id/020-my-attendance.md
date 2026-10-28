---
key: my-attendance
chapter: my-workspace
order: 20
title: My Attendance
summary: Lakukan Check-in dan Check-out, periksa riwayat kehadiran, dan minta HR mengoreksi jam yang salah atau terlewat.
requires: [general.my-attendance]
updated: 2026-10-27
---
`My Attendance` mencatat Check-in dan Check-out harian dari akun Anda sendiri dan menampilkan riwayat 30 hari terakhir.

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `My Attendance`.

## Sebelum Anda mulai

- Izinkan peramban membagikan lokasi Anda saat diminta. Sistem hanya mengambil lokasi perangkat pada saat Anda menekan tombol; tidak ada pelacakan di latar belakang.
- Gunakan koneksi aman (HTTPS) dan nyalakan layanan lokasi di perangkat Anda.

## Langkah demi langkah

### Check-in dan Check-out

1. Buka `My Attendance` dari sidebar. Anda juga dapat memakai tombol `Check-in` di `Dashboard`.
2. Pilih `Check-in` saat mulai bekerja. Tunggu pesan `Requesting location...` lalu `Saving...`.
3. Pilih `Check-out` saat selesai. Tombol ini baru aktif setelah Anda melakukan Check-in.
4. Periksa kartu `Check-in` dan `Check-out`. Kartu menampilkan jam, sumber, dan hasil lokasi.

Check-in dan Check-out dicatat satu kali per hari. Bila keduanya sudah terisi, halaman menyatakan Attendance hari ini sudah lengkap.

> [!NOTE]
> Sesuai aturan perusahaan, presensi di luar lokasi yang diizinkan akan ditandai untuk ditinjau HR atau ditolak. Jika ditolak, pesan akan menjelaskan alasannya.

### Membaca angka Anda

- Kartu bulanan menampilkan `Present This Month`, `Late`, `Work Hours`, dan `Overtime Hours`.
- Di bawah setiap presensi, `View on map` membuka titik yang tercatat. `GPS accuracy` dinilai Good, Fair, atau Low, dan `Technical details` berisi data mentahnya.
- Tabel `30-Day History` memuat `Date`, `In`, `Out`, `Work`, `Method`, `Location`, dan `Status` untuk setiap hari.

### Mengajukan koreksi

1. Pilih `Request correction`, atau pilih `Correct` pada sebuah baris di `30-Day History`.
2. Pilih `Date`. Anda hanya dapat mundur sejauh jumlah hari yang tertera di bawah kolom.
3. Isi `New check-in time`, `New check-out time`, atau keduanya.
4. Tulis `Reason` minimal 10 karakter, misalnya lupa Check-out setelah kunjungan klien.
5. Pilih `Submit correction`.

Hanya satu koreksi berstatus pending yang diizinkan per tanggal. Jika muncul pesan bahwa koreksi dinonaktifkan, hubungi HR secara langsung.

### Memantau atau membatalkan koreksi

1. Gulir ke `My Correction Requests`.
2. Baca `Status`: `Pending`, `Approved`, `Rejected`, atau `Cancelled`. HR dapat menambahkan catatan.
3. Pilih `Cancel` pada permintaan `Pending` jika sudah tidak diperlukan.

## Apa yang terjadi selanjutnya

HR menerima Notification atas koreksi Anda. Setelah HR menyetujui, catatan Anda diperbarui dan baris di `30-Day History` menampilkan `Correction` pada kolom `Method`. Jika HR menolak, baca catatannya dan ajukan permintaan baru bila perlu.

## Pertanyaan umum

**Lokasi gagal diambil.** Halaman menjelaskan penyebabnya, misalnya izin ditolak atau perlu koneksi aman. Izinkan lokasi untuk situs ini di pengaturan peramban lalu coba lagi. Jika halaman menyediakan `Test location access`, gunakan dan kirim hasilnya ke HR.

**Saya salah mencatat jam.** Presensi tidak dapat dibatalkan; gunakan `Request correction`.

## Topik terkait

- [[statuses-and-approvals]]
- [[my-overtime]]
- [[my-leave-permit]]
- [[navigating-ecosystem]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/attendance/my-attendance.blade.php, app/Http/Controllers/HR_General/MyAttendanceController.php, app/Http/Controllers/HR_General/AttendanceCorrectionController.php, app/Models/Attendance/AttendanceSetting.php, resources/views/hr-general/dashboard/attendance.blade.php -->

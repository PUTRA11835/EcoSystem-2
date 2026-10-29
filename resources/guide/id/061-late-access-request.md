---
key: late-access-request
chapter: work-service
order: 61
title: Akses Terlambat Timesheet
summary: Kapan Anda perlu meminta akses terlambat, dan bagaimana permintaan itu diputuskan bertingkat oleh Head lalu RPMO.
requires: [calendar.timesheets, rpmo.periods]
updated: 2026-10-29
---
Akses terlambat adalah izin sementara untuk mengisi timesheet pada periode yang sudah lewat. Izin ini diberikan per orang dan per periode, melalui dua persetujuan berurutan: Head tim Anda, lalu RPMO.

## Siapa yang menggunakan

| Peran dalam alur | Yang dilakukan | Siapa |
|---|---|---|
| Pemohon | Meminta akses terlambat dan menulis alasannya | Konsultan tim Project dan tim Support |
| Persetujuan 1 | Menyetujui atau menolak permintaan timnya sendiri | Pemegang izin `Manage Project Domain` atau `Manage Support Domain` (bawaan: Delivery Project Head dan Delivery Support Head) |
| Persetujuan 2 | Menyetujui dengan batas akses, atau menolak | Pemegang izin `Manage Global` (bawaan: Delivery RPMO Head) |

EC Administrator memegang ketiga izin itu. Izin dapat diatur di `Control Center` › `Menu Access`.

## Kapan memakai fitur ini

Fitur ini untuk kondisi khusus: Anda harus mengisi timesheet pada periode yang sudah berlalu dan sudah ditutup. Secara normal timesheet hanya dapat dicatat pada periode yang terbuka, yaitu periode berjalan dan satu periode sebelumnya. Di luar itu sistem menolak dan meminta Anda mengirim permintaan akses terlambat. Pakai fitur ini bila:

- Anda lupa mengisi timesheet dan periodenya sudah ditutup.
- Pekerjaan lama baru dapat dicatat sekarang, misalnya data aktivitas baru Anda terima.
- Sebuah timesheet lama perlu diperbaiki atau dilengkapi setelah periodenya lewat.

Alasan adanya alur bertingkat: periode yang sudah ditutup menjadi dasar laporan dan perhitungan MD. Membukanya kembali mengubah angka yang sudah dipakai, jadi Head memastikan pekerjaannya memang ada, dan RPMO memutuskan sampai kapan pintunya terbuka.

Anda tidak perlu fitur ini bila periodenya masih terbuka. Bila periode berjalan atau periode sebelumnya belum dibuka, cukup tunggu RPMO dan Head tim Anda membukanya.

## Sebelum Anda mulai

- Hanya anggota tim Project dan tim Support yang memakainya. Role lain, misalnya konsultan eksternal, tidak terkena pembatasan periode ini dan akan ditolak bila mencoba mengirim permintaan.
- Hanya ada satu permintaan per orang per periode. Selama permintaan masih menunggu, atau aksesnya masih berlaku, Anda tidak dapat mengirim yang baru.
- Alasan wajib diisi pada setiap langkah: oleh pemohon, oleh Head, dan oleh RPMO.

## Langkah demi langkah

### Sebagai konsultan (pemohon)

1. Buka `Calendar` › `Timesheets` lalu pilih `Request Late Access`.
2. Pada `Select Period`, pilih periodenya.
3. Tulis alasan di `Reason / Notes`. Jelaskan apa yang terlewat dan mengapa. Alasan yang jelas mempercepat keputusan.
4. Pilih `Submit Request`.
5. Pantau di tab `My History`. Statusnya `Pending Head`, lalu `Pending RPMO`. Setelah disetujui, tampil batas akses dan sisa waktunya.
6. Isi timesheet periode itu sebelum batas akses berakhir.

### Sebagai Head (persetujuan 1)

1. Anda mendapat notifikasi saat anggota tim mengirim permintaan. Buka `RPMO` › `Period Management` lalu pilih `Exception Requests`.
2. Anda hanya melihat permintaan tim yang Anda kelola, yang berstatus menunggu Head.
3. Baca alasannya. Periksa apakah pekerjaan itu memang ada dan wajar dicatat terlambat.
4. Setujui atau tolak, dan tulis catatan. Bila menolak, catatan itu dibaca pemohon.
5. Bila disetujui, permintaan berpindah ke RPMO dan pemohon diberi tahu.

### Sebagai RPMO (persetujuan 2)

1. Anda mendapat notifikasi saat Head menyetujui. Buka `Exception Requests` di `Period Management`.
2. Baca alasan pemohon dan catatan Head.
3. Untuk menyetujui, isi `Access Deadline` (harus di masa depan) dan `RPMO Notes`, lalu pilih `Approve & Set Deadline`. Beri waktu secukupnya agar pekerjaan selesai, tidak lebih.
4. Untuk menolak, isi alasannya lalu pilih `Reject`.

## Apa yang terjadi selanjutnya

- Menolak di langkah mana pun mengakhiri alur, dan pemohon menerima alasannya. Pemohon boleh mengirim ulang permintaan untuk periode itu.
- Setelah RPMO menyetujui, pemohon dapat mengisi periode itu, tanpa memandang status buka atau tutup periodenya, sampai batas akses.
- Akses hanya berlaku untuk orang, periode, dan tim yang diminta. Setelah batas akses lewat, periode itu tertutup lagi dan Anda perlu mengirim permintaan baru.
- Setiap permintaan menyimpan siapa yang menyetujui, kapan, dan catatannya. Semuanya terlihat di jendela `Exception Requests`.

## Pertanyaan umum

**Periode bulan lalu sudah ditutup. Apakah saya bisa meminta akses terlambat?** Ya. Semua periode yang sudah ditutup dapat diminta, termasuk bulan lalu. Setelah RPMO menyetujui, Anda dapat mengisinya sampai batas akses.

**Tombol `Request Late Access` tidak ada.** Tombol ini hanya muncul untuk konsultan yang mengisi timesheet. Head dan RPMO tidak memintanya, karena mereka yang memutuskan.

**Permintaan saya sudah disetujui Head tetapi belum bergerak.** Sekarang giliran RPMO. Statusnya `Pending RPMO`.

**Akses saya sudah habis sebelum selesai.** Kirim permintaan baru untuk periode yang sama, dan jelaskan mengapa waktunya kurang.

<!-- sources: app/Services/PeriodService.php, app/Http/Controllers/PeriodManagementController.php, app/Http/Controllers/TimesheetController.php, resources/views/calendar/timesheets.blade.php, resources/views/rpmo/periods/index.blade.php -->

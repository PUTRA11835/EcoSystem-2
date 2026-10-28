---
key: rpmo
chapter: work-service
order: 60
title: RPMO Period Management
summary: Buka dan tutup periode pelaporan agar Timesheet dapat diisi, serta tangani permintaan akses terlambat.
requires: [rpmo, rpmo.overview, rpmo.periods]
updated: 2026-10-27
---
RPMO (Resource & Project Management Office) mengatur periode pelaporan bulanan tempat tim delivery boleh mengisi Timesheet. Saat ini, fitur yang berfungsi pada menu ini adalah `Period Management`.

## Siapa yang menggunakan

Staf RPMO dan administrator mengelola periode secara global. Kepala Project dan kepala Support membuka serta menutup bagian periode untuk tim masing-masing dan memutuskan permintaan akses terlambat. Pengguna lain tidak melihat kontrol ini.

## Sebelum Anda mulai

- Halaman `Overview` masih berupa placeholder yang menyatakan modul sedang dibangun. Halaman itu hanya mengarahkan ke `Period Management`.
- Satu periode biasanya berjalan dari tanggal 21 bulan berjalan sampai tanggal 20 bulan berikutnya. Anda dapat mengubah tanggalnya saat membuat periode.

## Langkah demi langkah

### Membuat dan membuka periode

1. Buka `Work & Service` › `RPMO` › `Period Management`.
2. Klik `New Period`.
3. Pilih `Year` dan `Month`. Periksa atau ubah `Start Date` dan `End Date`.
4. Klik `Create Period`. Periode baru tampil sebagai `Pending Period` dengan label `Not Open`.
5. Klik `Open Period Globally`. Labelnya berubah menjadi `Open`.

### Membuka dan menutup bagian tim

Satu periode memiliki dua domain: `Project Domain` dan `Support Domain`.

1. Temukan domain yang Anda kelola pada kartu periode aktif.
2. Klik `Open Project Period` atau `Open Support Period` agar tim tersebut dapat mengisi Timesheet.
3. Setelah tim selesai, klik `Close Project Period` atau `Close Support Period`.
4. Konfirmasi dengan `Yes, Close Domain`.

### Menutup periode untuk semua

1. Pastikan kedua domain sudah ditutup. Halaman mengingatkan bahwa keduanya harus ditutup terlebih dahulu.
2. Klik `Close Period Globally`, lalu konfirmasi dengan `Yes, Close Period`.

Jika sebuah domain harus segera ditutup, RPMO atau administrator dapat memakai `Force Close Project` atau `Force Close Support`. Tindakan ini tidak dapat dibatalkan.

### Menangani permintaan akses terlambat

1. Klik `Exception Requests` pada kartu periode aktif.
2. Baca setiap permintaan di jendela `Late Access Requests`, lengkap dengan catatan pegawai.
3. Setujui atau tolak. Saat menyetujui, tetapkan `Access Deadline` dan tambahkan catatan Anda. Kepala menyetujui lebih dulu; RPMO kemudian menyetujui dan menetapkan batas waktunya.

### Tindakan lain

- Gunakan menu pada baris di `Period History` untuk mengubah tanggal atau menghapus periode.
- Klik `Audit Log` untuk melihat siapa yang membuka, menutup, atau mengubah periode dan kapan.

## Apa yang terjadi selanjutnya

- Anggota tim hanya dapat mengisi Timesheet untuk periode yang terbuka secara global dan domainnya terbuka. Periode di luar bulan berjalan dan bulan sebelumnya memerlukan permintaan akses terlambat yang disetujui.
- Pegawai dan kepala menerima Notification tentang perkembangan permintaan.

## Pertanyaan umum

**Saya tidak bisa mengirim Timesheet bulan lalu.** Periodenya mungkin sudah ditutup. Minta kepala Anda menyetujui permintaan akses terlambat.

**Saya tidak melihat tombol buka dan tutup.** Tombol ini hanya tampil untuk RPMO, administrator, dan, pada domain masing-masing, para kepala.

## Topik terkait

- [[delivery]]
- [[my-calendar-timesheet]]
- [[report-delivery]]

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, app/Services/PeriodService.php, resources/views/rpmo/rpmo.blade.php, resources/views/rpmo/periods/index.blade.php -->

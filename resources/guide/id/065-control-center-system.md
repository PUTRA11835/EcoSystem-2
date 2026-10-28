---
key: control-center-system
chapter: administration
order: 65
title: Control Center - Alat Sistem
summary: Kelola sesi aktif, pekerjaan latar belakang yang gagal, Backup dan Export, suara notifikasi, dan model AI.
requires: [control-center.sessions, control-center.failed-jobs, control-center.backup, control-center.sounds, control-center.ai-settings]
updated: 2026-10-27
---
Topik ini membahas alat sistem di `Control Center`: `Active Sessions`, `Failed Jobs`, `Backup & Export`, `Notif Sounds`, dan `AI Settings`. Untuk halaman pemantauan lihat [[control-center]].

## Siapa yang menggunakan

Hanya administrator. Setiap alat memiliki akses sendiri, jadi Anda hanya melihat halaman yang diizinkan untuk Role Anda.

> [!WARNING]
> Alat ini bekerja pada seluruh sistem dan data orang lain. Baca konfirmasi sebelum melanjutkan. Sebagian besar tindakan tidak dapat dibatalkan.

## Langkah demi langkah

### Active Sessions

`Active Sessions` menampilkan siapa yang sedang masuk, lengkap dengan pengguna, browser atau perangkat, alamat IP, dan aktivitas terakhir.

1. Buka `Control Center` › `Active Sessions`.
2. Klik `Force Logout` pada sebuah baris. Jendela `Session Detail` terbuka.
3. Konfirmasi `Force Logout` untuk mengakhiri sesi itu. Orang tersebut harus masuk kembali dan pekerjaan yang belum disimpan bisa hilang.
4. `Force Logout All Others` mengakhiri semua sesi kecuali sesi Anda.

Sesi Anda sendiri dan sesi yang dilindungi tidak dapat diakhiri dari baris. Gunakan ini bila akun mungkin disalahgunakan atau perangkat hilang.

### Failed Jobs

Beberapa tugas berjalan di latar belakang, seperti mengirim notifikasi atau menghitung payroll besar. `Failed Jobs` mencantumkan yang gagal.

1. Buka `Control Center` › `Failed Jobs`.
2. Baca `Queue`, `Failed At`, dan `Error` sebuah pekerjaan. Buka baris untuk melihat `Exception Detail`.
3. Klik `Retry` untuk menjalankan satu pekerjaan lagi, atau `Retry All`.
4. Klik `Delete` untuk membuang satu pekerjaan, atau `Clear All` untuk membuang semuanya.

> [!IMPORTANT]
> Membuang pekerjaan tidak dapat dibatalkan. Bila pekerjaan yang sama gagal lagi setelah `Retry`, tanyakan tim teknis sebelum mencoba lebih banyak.

### Backup & Export

1. Buka `Control Center` › `Backup & Export`.
2. Klik `Create Backup Now` untuk membuat backup database. Backup muncul di `Existing Backups`, tempat Anda dapat mengunduh atau menghapusnya.
3. Pada bagian export (misalnya `Employee Data`, `Customer Data`, `Ticket Data`), ekspor datanya sebagai berkas.
4. Untuk import, unduh template, isi, letakkan berkas di area import, lalu periksa daftar `Import Errors`. Baris yang tidak dapat dicocokkan dilewati.

> [!WARNING]
> Berkas backup dan data hasil export berisi informasi pribadi dan perusahaan. Simpan dengan aman dan hapus berkas yang tidak lagi diperlukan. Import mengubah data sungguhan, jadi periksa template terlebih dahulu dan coba dengan berkas kecil.

### Notif Sounds

1. Buka `Control Center` › `Notif Sounds`.
2. Di `Upload New Sound`, isi `Display Name`, pilih berkas audio (WAV, MP3, OGG, atau AAC, maksimal 3 MB), lalu unggah.
3. Gunakan `Preview` untuk mendengarkan. Hapus suara yang tidak diperlukan. Suara bawaan dilindungi dan tidak dapat dihapus.

Suara yang diunggah tersedia sebagai pilihan nada peringatan notifikasi.

### AI Settings

1. Buka `Control Center` › `AI Settings`.
2. Untuk setiap asisten, pilih `Model` dan `Thinking / reasoning effort`.
3. Simpan pengaturan.

Model yang berbeda mengubah kualitas, kecepatan, dan biaya asisten untuk semua orang. Ubah hanya berdasarkan keputusan bersama.

## Apa yang terjadi selanjutnya

Perubahan langsung berlaku untuk semua orang. Sesi yang diakhiri keluar pada tindakan berikutnya.

## Pertanyaan umum

**Seorang Employee tidak bisa masuk setelah force logout.** Force logout hanya mengakhiri sesi. Mereka dapat masuk kembali dengan akun biasa.

**Mengapa saya tidak melihat `AI Settings`?** Halaman itu memerlukan akses tersendiri, biasanya hanya dipegang administrator tertinggi. Hubungi administrator Anda.

## Topik terkait

- [[control-center]]
- [[roles-and-menu-access]]
- [[ai-tools]]

<!-- sources: routes/web.php, resources/views/admin/sessions.blade.php, resources/views/admin/failed-jobs.blade.php, resources/views/admin/backup.blade.php, resources/views/admin/sounds.blade.php, resources/views/admin/ai-settings.blade.php -->

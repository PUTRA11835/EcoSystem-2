---
key: control-center-security
chapter: administration
order: 62
title: Control Center - Keamanan dan halaman platform
summary: Pantau aktivitas mencurigakan, periksa tugas terjadwal, ikuti pemakaian AI, dan atur verifikasi 2 langkah serta tema musiman.
requires: [control-center.security, control-center.schedule-monitor, control-center.ai-usage, control-center.two-factor-enforcement, control-center.seasonal-theme]
updated: 2026-10-28
---
Halaman `Control Center` ini menjaga sistem tetap aman dan sehat. Halaman-halaman ini melengkapi topik pemantauan dan alat sistem, dan hanya untuk administrator.

## Siapa yang menggunakan

Administrator. Setiap halaman memiliki akses sendiri, jadi Anda hanya melihat halaman yang diizinkan untuk role Anda.

> [!IMPORTANT]
> Halaman ini menampilkan informasi aktivitas orang dan biaya sistem. Gunakan hanya untuk administrasi dan keamanan, dan jangan bagikan apa yang Anda lihat.

## Sebelum Anda mulai

- Ketahui apa yang ingin diperiksa: seseorang, rentang waktu, atau jenis kejadian. Persempit daftar dengan filter terlebih dahulu.

## Langkah demi langkah

### Security Center

1. Buka `Control Center` › `Security Center`.
2. Baca ringkasan dan daftar kejadian. Percobaan masuk gagal berulang, input mencurigakan, dan akses tidak biasa ke data sensitif ditandai di sini.
3. Jika sebuah akun terkunci karena percobaan gagal berulang, pakai `Unlock` setelah Anda yakin pemiliknya yang sedang masuk.

### Schedule Monitor

1. Buka `Control Center` › `Schedule Monitor`.
2. Baca tiap tugas terjadwal beserta waktu jalan terakhir dan statusnya.
3. Tugas yang menampilkan `Never Run` belum pernah berjalan di server ini. Periksa bahwa scheduler berjalan di sana.

### AI Usage

1. Buka `Control Center` › `AI Usage`.
2. Pilih periode lalu baca pemakaian token dan estimasi biaya per model, per fitur, dan per pengguna.
3. Isi saldo dan tanggal mulai untuk melihat estimasi sisa saldo. Ini hanya estimasi: penyedia tidak melaporkan saldo Anda ke EcoSystem.

### Two-Factor Enforcement

1. Buka `Control Center` › `Two-Factor Enforcement`.
2. Pilih role yang wajib memakai verifikasi 2 langkah.
3. Simpan. Orang di role itu dipandu menyiapkannya saat masuk berikutnya.

### Seasonal Theme

1. Buka `Control Center` › `Seasonal Theme`.
2. Pilih tema musiman bawaan untuk dashboard dan halaman masuk, serta suaranya bila ada.
3. Simpan. Setiap pengguna tetap dapat mengubah tampilannya sendiri di `Settings`.

## Apa yang terjadi selanjutnya

Perubahan berlaku untuk semua orang. Membuka kunci akun membuat orang tersebut dapat langsung masuk lagi.

## Pertanyaan umum

**Sebuah akun terus terkunci.** Lihat kejadian di `Security Center`. Jika percobaannya bukan dari pemilik, beri tahu kontak keamanan Anda.

**Mengapa saldo AI berbeda dengan konsol penyedia?** Angka di EcoSystem adalah estimasi dari pemakaian yang tercatat. Konsol penyedia adalah angka resmi.

## Topik terkait

- [[sign-in-and-security]]

<!-- sources: routes/web.php, resources/views/admin/security-center.blade.php, resources/views/admin/schedule-monitor.blade.php, resources/views/admin/ai-usage.blade.php, resources/views/admin/two-factor-enforcement-settings.blade.php, resources/views/admin/seasonal-theme-settings.blade.php -->

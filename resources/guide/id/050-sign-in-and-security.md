---
key: sign-in-and-security
chapter: getting-started
order: 50
title: Masuk dan keamanan akun
summary: Cara masuk, mengganti password, memakai verifikasi 2 langkah, dan menjaga akun tetap aman.
requires: []
updated: 2026-10-28
---
Topik ini menjelaskan cara masuk ke EcoSystem, tempat mengganti password, fungsi verifikasi 2 langkah, dan kebiasaan yang menjaga akun Anda tetap aman.

## Siapa yang menggunakan

Semua orang yang masuk ke EcoSystem.

## Sebelum Anda mulai

- Siapkan email atau employee ID dan password Anda sendiri. Keduanya hanya milik Anda.
- Jika akun Anda memakai verifikasi 2 langkah, siapkan aplikasi authenticator di ponsel Anda.

## Langkah demi langkah

### Masuk

![Halaman masuk EcoSystem dengan kolom email atau employee ID, kolom password, dan tombol Sign In](images/guide/sign-in-login.png)

*Tangkapan layar dengan data contoh.*

1. Buka alamat EcoSystem yang diberikan perusahaan Anda.
2. Isi `Email or Employee ID` dan `Password` Anda.
3. Centang `Keep me signed in` hanya di komputer yang hanya Anda pakai.
4. Pilih `Sign In`.
5. Jika diminta, masukkan kode 6 angka dari aplikasi authenticator, lalu konfirmasi.

Kode berganti setiap 30 detik. Jika ditolak, tunggu kode berikutnya lalu coba lagi.

### Jika Anda lupa password

1. Di halaman masuk, pilih `Forgot password?`.
2. Ikuti petunjuk yang dikirim ke email terdaftar Anda.

### Mengganti password

![Tab Security di Settings dengan formulir password, two-factor authentication, dan active sessions](images/guide/settings-security.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Settings` lalu pilih tab `Security`.
2. Pada bagian `Password`, isi password baru (minimal 8 karakter) lalu ulangi di `Confirm new password`.
3. Pilih `Update password`.

### Memakai verifikasi 2 langkah

1. Di tab `Security` yang sama, temukan `Two-factor authentication`. Statusnya `Not configured` sampai Anda menyiapkannya.
2. Pilih `Enable 2FA` lalu ikuti langkah di layar.
3. Simpan kode pemulihan yang ditampilkan di tempat aman yang hanya dapat Anda buka.

Sebagian role wajib memakai verifikasi 2 langkah. Jika role Anda termasuk, Anda akan dipandu menyiapkannya saat masuk.

### Memeriksa tempat Anda masuk

Bagian `Active sessions` di tab `Security` mendaftar sesi akun Anda. Baris bertanda `Current session` adalah browser yang sedang Anda pakai.

> [!IMPORTANT]
> Jangan pernah membagikan password atau kode verifikasi kepada siapa pun, termasuk rekan kerja, atasan, atau orang yang mengaku dari tim IT. Jangan menuliskannya di chat, email, atau catatan ticket.

> [!TIP]
> Keluar (sign out) bila Anda meninggalkan komputer bersama.

## Apa yang terjadi selanjutnya

Percobaan masuk yang gagal berulang kali akan mengunci akun untuk sementara demi melindunginya. Tunggu kunci berakhir, atau hubungi administrator jika Anda masih terblokir.

## Pertanyaan umum

**Saya tidak menerima kode verifikasi.** Kode berasal dari aplikasi authenticator, bukan dari email atau SMS. Periksa bahwa jam di ponsel Anda sudah benar, lalu coba kode berikutnya.

**Saya curiga akun saya dipakai orang lain.** Segera ganti password dan beri tahu administrator.

<!-- sources: resources/views/auth/login.blade.php, resources/views/settings/index.blade.php, routes/web.php -->

---
key: guide-ec-user
chapter: reference
order: 90
title: Panduan EC User
summary: Yang dapat dilakukan role EC User: melihat dashboard Home, membaca kalender dan timesheet, memakai AI Research, dan mengelola akun sendiri.
requires: ["@role:EC User"]
updated: 2026-10-28
---
Panduan ini untuk role EC User. Ini adalah role dasar dengan menu yang sedikit. Banyak orang memegangnya bersama role lain, dan bila demikian Anda juga melihat panduan role tersebut.

## Siapa yang menggunakan

Karyawan dengan role EC User. Menu Anda adalah `Home`, `AI Research` dan `Calendar` (`Events` dan `Timesheets`), ditambah `Settings` dan `User Guide`.

## Sebelum Anda mulai

- Role Anda dapat membaca kalender dan timesheet Anda, tetapi tidak punya tombol untuk membuat event atau mencatat waktu. Ini memang pengaturan role, bukan kerusakan.
- Jangan memasukkan kredensial atau data pribadi customer ke AI Research.

## Langkah demi langkah

### Mulai dari Home

1. Buka `Home` setelah Anda masuk. Pakai `Command Center` untuk pintasan yang disematkan dan baca kartu di bawahnya. Lihat [[home-dashboard]].

![Dashboard Home milik EC User, dengan kartu dan ikhtisar tiket](images/guide/home-dashboard.png)

*Tangkapan layar dengan data contoh.*

### Baca kalender

1. Buka `Calendar` › `Events`. Pakai `Today`, `Month`, `Week` dan `Day` untuk mengganti tampilan, dan panah untuk berpindah periode.
2. Pilih sebuah event untuk membacanya.

### Periksa timesheet Anda

1. Buka `Calendar` › `Timesheets`. Kartu menghitung entri Anda menurut status: `Total`, `Draft`, `Submitted`, `Approved` dan `Rejected`.
2. Pakai label bulan di kanan atas untuk melihat apakah periodenya terbuka, dan `Reset Filter` untuk menghapus filter Anda.
3. Bila Anda perlu mencatat waktu, minta administrator menambahkan izinnya ke role Anda. Lihat [[calendar-timesheet]].

### Jaga akun Anda tetap aman

Buka `Settings` lalu tab `Security` untuk mengganti password dan mengatur `Two-factor authentication`. Lihat [[sign-in-and-security]].

## Apa yang terjadi selanjutnya

Event dan entri timesheet menampilkan keadaan terbarunya setiap kali Anda membuka halaman.

## Pertanyaan umum

**Saya tidak dapat membuat event atau timesheet.** Role EC User tidak menyertakan tombol itu. Tanyakan kepada administrator bila Anda membutuhkannya.

**Menu saya lebih sedikit dari rekan kerja.** Menu mengikuti role masing-masing.

## Topik terkait

- [[home-dashboard]]
- [[calendar-timesheet]]
- [[sign-in-and-security]]

<!-- sources: routes/web.php, resources/views/home, resources/views/calendar/timesheets.blade.php, resources/views/calendar/events.blade.php -->

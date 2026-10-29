---
key: ticket-teams-guest
chapter: work-service
order: 17
title: Bergabung ke chat ticket sebagai tamu
summary: Untuk consultant external: terima undangan Eclectic dan buka chat ticket di browser, tanpa akun Teams.
requires: [tickets.inbox]
updated: 2026-10-28
---
Topik ini untuk consultant external yang bekerja dengan email di luar Eclectic, misalnya alamat Gmail. Anda bergabung ke group chat ticket sebagai tamu (guest) Eclectic Company. Anda tidak perlu memasang Teams atau membuat akun Microsoft. Cukup browser dan akses ke email Anda.

Pertama kali butuh sekitar lima menit. Ikuti langkahnya secara berurutan. Kegunaan chat dan cara isinya sejalan dengan ticket dijelaskan di [[ticket-teams-chat]].

## Langkah 1. Buka email undangan

Anda menerima email dari EC Support dengan subjek `Group chat Microsoft Teams:` diikuti nama chat. Email ini bisa datang beberapa menit setelah Anda ditugaskan ke ticket. Bila tidak ada di kotak masuk, cari di folder spam lalu tandai sebagai bukan spam, supaya undangan berikutnya masuk dengan normal.

Email pertama berisi dua tombol. Pakai secara berurutan: terima undangan dulu, baru buka chat.

![Email undangan pertama dari EC Support dengan tombol Terima undangan Eclectic Company dan Buka group chat di Teams](images/guide/guest-invite-email.png)

*Tangkapan layar dengan data contoh.*

## Langkah 2. Terima undangan

Pilih `Terima undangan Eclectic Company`. Halaman masuk Microsoft terbuka dengan alamat email Anda. Pilih `Send code` untuk menerima kode lewat email.

![Halaman masuk Microsoft yang menampilkan alamat email tamu dan tombol Send code](images/guide/guest-signin.png)

*Tangkapan layar dengan data contoh.*

## Langkah 3. Ambil kode verifikasi

Microsoft mengirim email `Account verification code` dari Eclectic Company (via Microsoft) ke alamat yang sama. Buka email itu dan catat kodenya. Kode hanya berlaku 30 menit.

![Email Account verification code dari Eclectic Company via Microsoft berisi kode verifikasi](images/guide/guest-code-email.png)

*Tangkapan layar dengan data contoh.*

## Langkah 4. Masukkan kode

Kembali ke halaman masuk, ketik kodenya, lalu lanjutkan.

## Langkah 5. Setujui izin

Microsoft meminta Anda mengizinkan Eclectic Company membaca nama dan alamat email Anda. Pilih `Accept`. Bila ditanya apakah tetap masuk, pilih ya hanya di perangkat milik sendiri.

Langkah 2 sampai 5 hanya perlu sekali. Berikutnya Anda cukup masuk dengan kode email saja.

## Langkah 6. Buka group chat

Kembali ke email undangan, lalu pilih `Buka group chat di Teams`. Bila ditawari pilihan, pilih `Continue on this browser`. Cara ini paling mudah dan tidak perlu memasang apa pun.

## Langkah 7. Bekerja di chat

Teams terbuka di browser dengan chat ticket yang dipilih. Daftar chat hanya berisi chat ticket tempat Anda menjadi anggota. Anda bisa membaca chat dari awal, termasuk kartu `Tiket baru` dan pesan dari customer, lalu membalas seperti anggota lain.

![Group chat ticket terbuka di Teams pada browser untuk tamu, dengan kartu Tiket baru dan pesan dari customer](images/guide/guest-chat-browser.png)

*Tangkapan layar dengan data contoh.*

Spanduk yang menawarkan aplikasi Teams desktop boleh ditutup. Untuk ticket berikutnya, email yang dikirim lebih singkat dan hanya berisi tombol untuk membuka chat.

> [!TIP]
> Bila aplikasi Teams desktop menampilkan pesan "We can't take you to that message because it's in a chat you're not in", berarti aplikasi sedang memakai akun Anda yang lain. Pilih foto profil di kanan atas, pindah ke organisasi `Eclectic Company (Guest)`, lalu buka tautannya lagi. Bila organisasi itu tidak muncul, gunakan Teams di browser.

## Pertanyaan umum

**Saya tidak menerima email apa pun.** Periksa folder spam lebih dulu. Lalu pastikan alamat email di profil EcoSystem Anda adalah alamat yang Anda pakai, dan minta helpdesk memeriksanya bila belum.

**Kode verifikasi tidak bisa dipakai.** Kode kedaluwarsa setelah 30 menit. Minta kode baru di halaman masuk yang sama dan pakai email yang paling baru.

**Apakah saya perlu memasang Teams?** Tidak. Teams di browser sudah cukup. Bila lebih suka aplikasinya, masuk di sana dengan email yang sama lalu pindah ke `Eclectic Company (Guest)`.

**Saya sudah ditugaskan ke ticket tetapi belum ada di chatnya.** Tamu yang baru pertama kali diundang masuk beberapa menit kemudian. Bila setelah itu Anda masih belum masuk, minta ticket lead atau helpdesk menambahkan Anda di Teams.

## Topik terkait

- [[ticket-teams-chat]]

<!-- sources: app/Services/Teams/TeamsGuestService.php, app/Services/Teams/TeamsChatService.php, app/Console/Commands/AddPendingTeamsGuests.php, docs/power-automate-integration.md -->

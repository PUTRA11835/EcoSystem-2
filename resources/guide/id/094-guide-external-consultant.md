---
key: guide-external-consultant
chapter: reference
order: 94
title: Panduan External Consultant
summary: Rutinitas konsultan eksternal: kerjakan tiket Anda, usulkan mandays, dan catat waktu Anda.
requires: ["@role:External Consultant"]
updated: 2026-10-28
---
Panduan ini untuk role External Consultant, bagi konsultan yang mengerjakan tiket customer tanpa menjadi bagian tim tetap.

## Siapa yang menggunakan

Konsultan eksternal. Menu Anda adalah `Home`, `AI Research`, `Ticket` dan `Calendar` (`Events` dan `Timesheets`), ditambah `Settings` dan `User Guide`.

## Sebelum Anda mulai

- Tiket dapat menampilkan informasi kredensial customer. Pakai hanya untuk pekerjaan dan jangan pernah menyalinnya ke chat, catatan, email, atau dokumen lain. Jangan juga memasukkannya ke alat AI.
- Menu Anda lebih sedikit dari staf. Jika sebuah tombol tidak ada, itu bukan bagian dari role Anda.

## Langkah demi langkah

### Setiap hari

1. Buka `Ticket`. Kartu di atas menghitung tiket menurut status, dan `Reset Filter` menghapus filter Anda. Pilih sebuah tiket untuk membukanya.
2. Balas customer atau tulis `Internal Note` saat ada perkembangan. Catatan internal hanya dilihat tim, dan balasan dilihat customer, jadi pastikan yang Anda pakai.
3. Jaga status sesuai keadaan sebenarnya, misalnya `Wait Customer` saat menunggu jawaban.
4. `AI Summarize` dan tombol riset AI pada tiket dapat membantu untuk percakapan yang panjang. Lihat [[ticket-ai-tools]].

### Usulkan mandays

1. Buka bagian `Mandays` pada tiket lalu pilih `Propose Mandays` atau `Propose Resolution Days`.
2. Simpan draf, periksa, lalu kirim. Lihat [[ticket-mandays]].

### Catat waktu Anda

![Halaman Timesheets dengan entri dan statusnya](images/guide/timesheets.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Calendar` › `Timesheets`. Pilih `Create Timesheet` untuk menambah entri.
2. Pakai filter `All`, `Project`, `Support` atau `Office` untuk melihat satu jenis pekerjaan.
3. Bila periode sudah ditutup, pakai `Request Late Access`. Lihat [[calendar-timesheet]].

## Apa yang terjadi selanjutnya

Timesheet yang Anda kirim diteruskan ke penyetuju. Waktu yang disetujui masuk ke laporan yang dibaca manajer Anda. Usulan mandays diteruskan ke Helpdesk atau atasan untuk ditinjau.

## Pertanyaan umum

**Sebuah tiket tidak ada di daftar saya.** Anda melihat tiket yang diizinkan oleh akses Anda. Minta ticket lead menambahkan Anda sebagai anggota.

**Saya tidak dapat membuat tiket atau menugaskannya.** Tindakan itu milik Helpdesk dan para Head.

## Topik terkait

- [[ticket-mandays]]
- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/calendar/timesheets.blade.php -->

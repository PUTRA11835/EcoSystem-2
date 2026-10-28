---
key: guide-ds-user
chapter: reference
order: 80
title: Panduan Delivery Support User
summary: Rutinitas harian konsultan support: ambil ticket, kerjakan, usulkan mandays, dan catat waktu.
requires: ["@role:Delivery Support User"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Support User, yaitu konsultan yang mengerjakan ticket customer. Isinya rutinitas harian yang sederhana dan tautan ke topik rinci.

## Siapa yang menggunakan

Konsultan dengan role Delivery Support User. Yang dapat Anda lakukan bergantung pada role: misalnya Anda dapat mengambil ticket dan mengusulkan mandays, tetapi tidak membuat ticket atau menugaskan orang lain.

## Sebelum Anda mulai

- Menu Anda mengikuti role. Jika sebuah tombol di bawah tidak ada, berarti bukan bagian role Anda. Tanyakan kepada Helpdesk atau kepala Anda.
- Anda mungkin melihat informasi credential customer pada ticket. Pakai hanya untuk pekerjaan, dan jangan pernah menyalinnya ke chat, catatan, email, atau dokumen lain.

## Langkah demi langkah

### Setiap hari

1. Buka `My Tasks` untuk melihat pekerjaan yang ditugaskan kepada Anda. Kartu di bagian atas menampilkan `Active Tickets`, `Alloc MD`, `Remaining`, `Workload`, dan `Load Score` Anda.

![Halaman My Tasks dengan kartu ringkasan dan daftar ticket yang Anda pimpin](images/guide/my-tasks.png)

*Tangkapan layar dengan data contoh.*


2. Buka `Work & Service` › `Ticket` dan tab `My Tickets`. Balas customer atau tulis `Internal Note` bila ada perkembangan.
3. Perbarui progres ticket dengan `Edit Progress` di `My Tasks`, dan jelaskan apa yang sudah selesai dan langkah berikutnya.
4. Catat jam kerja di `Calendar` › `Timesheets` sebelum hari berakhir. Lihat [[calendar-timesheet]].

### Mengambil ticket baru

1. Buka tab `Unassigned` dan pilih ticket yang sesuai keahlian Anda.
2. Pilih `Take This Ticket`. Lihat [[ticket-assignment]].

### Mengerjakan ticket

- `Internal Note` hanya dilihat tim Anda. Balasan ke customer dilihat customer, jadi periksa dulu mana yang Anda pakai sebelum mengirim.
- Atur status sesuai kenyataan, misalnya `Wait Customer` saat menunggu customer. Status yang benar menjaga SLA tetap adil.
- Pakai `Team Members` untuk mengajak rekan yang perlu membantu.
- `AI Summarize` dan `Ask AI` dapat merangkum percakapan panjang. Jangan memasukkan credential ke dalamnya. Lihat [[ticket-ai-tools]].

### Mengusulkan mandays

1. Buka bagian `Mandays` lalu pilih `Propose Mandays` atau `Propose Resolution Days`.
2. Simpan `Draft`, periksa, lalu kirim.
3. Helpdesk meninjau Customer Mandays dan kepala menyetujui Resolution Days. Lihat [[ticket-mandays]].

## Apa yang terjadi selanjutnya

Hari yang disetujui menjadi dasar beban kerja Anda dan laporan yang dibaca atasan. Timesheet yang Anda kirim menuju kepala Anda untuk disetujui.

## Pertanyaan umum

**Saya tidak dapat membuat ticket atau menugaskannya.** Aksi itu milik Helpdesk dan kepala.

**Ticket yang saya kerjakan tidak ada di `My Tasks`.** Hanya ticket yang Anda pegang sebagai ticket lead atau anggota tim yang tampil. Minta ticket lead menambahkan Anda di `Team Members`.

## Topik terkait

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/ticket/task.blade.php, resources/views/calendar/timesheets.blade.php -->

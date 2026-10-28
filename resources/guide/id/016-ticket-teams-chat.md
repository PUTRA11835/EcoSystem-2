---
key: ticket-teams-chat
chapter: work-service
order: 16
title: Group chat ticket di Microsoft Teams
summary: Kenapa Anda tiba-tiba masuk ke group chat Teams, untuk apa chat itu, dan bagaimana isinya sejalan dengan ticket.
requires: [tickets.inbox]
updated: 2026-10-28
---
Setiap ticket yang sudah divalidasi kini punya group chat sendiri di Microsoft Teams. EcoSystem yang membuat chat itu dan memasukkan orang-orang yang mengerjakan ticket tersebut. Jadi bila tiba-tiba muncul group chat baru di Teams Anda tanpa pemberitahuan, itu adalah chat ticket dan Anda memang sengaja dimasukkan.

## Siapa yang menggunakan

Semua yang mengerjakan ticket: consultant, ticket lead, ticket member, Module Lead, tim Delivery Support ticket tersebut, dan helpdesk. Consultant external yang memakai email di luar Eclectic, misalnya Gmail, bergabung sebagai tamu (guest) tanpa akun Teams. Langkahnya ada di [[ticket-teams-guest]].

## Sebelum mulai

- Nama chat mengikuti ticket: nomor ticket, spasi, tanda hubung, lalu subjek, misalnya `26109001 - Error posting GR in MIGO`. Pakai nomornya untuk mencari ticket di `Ticket`.
- Chat ini hanya untuk tim internal. Customer tidak pernah ada di dalamnya dan tidak bisa melihatnya, meskipun percakapan dengan customer ikut disalin ke chat agar tim bisa membacanya.
- Satu ticket punya satu chat. Jangan membuat grup kedua secara manual untuk ticket yang sama.

> [!IMPORTANT]
> Pesan di chat disalin ke ticket sebagai internal note. Jangan pernah menulis password, credential customer, atau rahasia lain di chat, sama seperti Anda tidak menuliskannya di catatan ticket.

## Kapan Anda dimasukkan

Anda dimasukkan otomatis, tanpa perlu meminta, pada salah satu saat berikut:

1. **Ticket divalidasi.** Saat helpdesk menyetujui ticket di `Ticket Validation`, chat dibuat. Isinya mula-mula Module Lead dari modul ticket dan tim Delivery Support ticket itu (Delivery Owner, Support Manager, CO PM, dan Support Admin), ditambah beberapa anggota tetap.
2. **Anda ditugaskan ke ticket.** Saat seseorang menjadikan Anda ticket lead, atau menambahkan Anda lewat `Add Member`, Anda dimasukkan ke chat ticket itu.

Chat baru muncul di urutan teratas daftar chat Anda, dengan nama sesuai ticket.

![Daftar chat Teams dengan group chat ticket baru di urutan teratas, bernama nomor dan subjek ticket](images/guide/teams-new-chat.png)

*Tangkapan layar dengan data contoh.*

Pesan pertama di chat adalah kartu `Tiket baru` berisi detail ticket dan tombol `Buka di EcoSystem` untuk membuka ticket. Di bawahnya, Module Lead disebut dengan @mention dan diminta menindaklanjuti ticket.

![Kartu Tiket baru di group chat ticket berisi detail ticket dan tombol Buka di EcoSystem](images/guide/teams-ticket-card.png)

*Tangkapan layar dengan data contoh.*

Bila Anda yang disebut, Teams menampilkan notifikasi `EC Support @mentioned you` dengan nama chat.

![Notifikasi Teams EC Support @mentioned you dengan nama chat ticket](images/guide/teams-mention-toast.png)

*Tangkapan layar dengan data contoh.*

Bila Anda dimasukkan ke chat yang sudah berjalan, Teams menampilkan baris bahwa Anda ditambahkan dan seluruh riwayat chat dibagikan. Anda bisa membaca chat dari awal, jadi Anda bisa mengikuti diskusi yang sudah berjalan.

![Group chat ticket dengan pesan dari customer lalu baris bahwa seorang anggota ditambahkan dan riwayat chat dibagikan](images/guide/teams-member-added.png)

*Tangkapan layar dengan data contoh.*

## Bagaimana chat dan ticket tetap sejalan

**1. Internal note ditulis di EcoSystem.** Di ticket, pilih `Internal Note`, tulis pesan, lalu kirim.

![Percakapan ticket di EcoSystem dengan internal note yang baru dikirim](images/guide/ticket-internal-note.png)

*Tangkapan layar dengan data contoh.*

**2. Anggota chat mendapat notifikasi.** Teams menampilkan notifikasi dari EC Support dengan nama penulis note.

![Notifikasi Teams dari EC Support berisi nama penulis internal note dan EcoSystem](images/guide/teams-note-toast.png)

*Tangkapan layar dengan data contoh.*

**3. Note muncul di chat.** Baris pertama pesan berisi nama penulis dan `EcoSystem`, disusul isi note.

![Group chat ticket dengan pesan dari customer dan internal note dari EcoSystem yang diawali nama penulis dan EcoSystem](images/guide/teams-chat-sync.png)

*Tangkapan layar dengan data contoh.*

**4. Balas langsung di Teams.** Anda juga bisa melampirkan berkas.

![Balasan yang diketik di Teams di bawah internal note, dengan lampiran berkas Excel](images/guide/teams-chat-reply.png)

*Tangkapan layar dengan data contoh.*

**5. Balasan masuk ke ticket.** Dalam sekitar satu menit, balasan dari Teams muncul di ticket sebagai internal note atas nama Anda. Berkas lampiran tampil sebagai tautan.

![Percakapan ticket dengan balasan dari Teams sebagai internal note atas nama penulisnya, dengan tautan berkas](images/guide/ticket-teams-notes.png)

*Tangkapan layar dengan data contoh.*

| Yang terjadi | Muncul di sini |
|---|---|
| Anda menulis `Internal Note` pada ticket di EcoSystem | Di chat Teams, sebagai pesan yang diawali nama Anda dan `EcoSystem` |
| Anda menulis pesan di chat Teams | Di ticket, sebagai internal note atas nama Anda, biasanya dalam satu menit |
| Customer mengirim email atau balasan ke ticket | Di chat Teams, sebagai pesan yang diawali `From Customer` dan alamat email customer. Hanya untuk dibaca tim |
| Anda membalas customer dengan `Send via Email` | Di chat Teams, sebagai pesan yang diawali nama Anda dan `Reply to Customer`. Balasan yang emailnya gagal terkirim tidak disalin |
| Anda melampirkan gambar | Di sisi lain sebagai gambar |
| Anda melampirkan berkas (PDF, Excel, dokumen) | Di sisi lain sebagai tautan ke berkas itu |
| Anda menjadwalkan meeting dari ticket | Di chat Teams, sebagai pengumuman meeting untuk tim |

Yang **tidak** disalin:

- Pesan sistem dan pesan SLA.
- Edit dan hapus. Bila Anda mengubah atau menghapus pesan di satu sisi, salinan di sisi lain tetap seperti semula. Tulis koreksinya sebagai pesan baru.
- Kutipan saat Anda membalas pesan tertentu di Teams. Balasannya masuk ke ticket sebagai note biasa tanpa kutipan.

> [!TIP]
> Pesan `From Customer` di chat hanya salinan. Membalasnya di Teams tidak mengirim apa pun ke customer, balasan itu masuk ke ticket sebagai internal note. Untuk menjawab customer, tulis balasan di ticket pada EcoSystem.

Setelah ticket berstatus `Closed` atau `Cancelled`, pesan chat yang baru tidak lagi disalin ke ticket.

## Langkah selanjutnya

Kerjakan ticket seperti biasa. Berdiskusi di chat atau menulis internal note di EcoSystem, karena keduanya tercatat di riwayat ticket. Balasan ke customer tetap ditulis di ticket pada EcoSystem.

## Pertanyaan umum

**Saya dimasukkan ke group chat yang tidak saya kenal.** Lihat nomor di awal nama chat dan buka ticket itu di `Ticket`. Anda ada di chat karena Anda ticket lead, ticket member, Module Lead, atau bagian dari tim Delivery Support.

**Saya sudah ditugaskan ke ticket tetapi tidak ada di chatnya.** Chat baru dibuat saat ticket divalidasi. Bila setelah itu Anda masih belum masuk, minta ticket lead atau helpdesk menambahkan Anda di Teams.

**Bolehkah saya menambahkan orang ke chat sendiri?** Boleh, setiap anggota bisa menambahkan orang dan mengganti nama chat. Satu chat menampung paling banyak 20 orang.

**Saya sudah dikeluarkan dari ticket tetapi masih ada di chat.** Mengeluarkan member dari ticket tidak mengeluarkannya dari chat. Anda bisa keluar dari chat sendiri di Teams.

**Pesan saya di Teams tidak muncul di ticket.** Tunggu satu menit, lalu muat ulang ticket. Pesan tidak disalin bila ticket sudah ditutup atau dibatalkan.

## Topik terkait

- [[ticket-teams-guest]]

<!-- sources: app/Services/Teams/TeamsChatService.php, app/Services/Teams/TeamsGuestService.php, app/Services/Teams/TeamsInboundService.php, app/Services/Teams/TeamsOutboxService.php, app/Services/PowerAutomateService.php, docs/teams-chat-sync-design.md, docs/teams-sync-parity-design.md, docs/power-automate-integration.md -->

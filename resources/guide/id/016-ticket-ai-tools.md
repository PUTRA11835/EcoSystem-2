---
key: ticket-ai-tools
chapter: work-service
order: 16
title: AI Summarize dan Ask AI pada tiket
summary: Ringkas tiket yang panjang dalam tiga bagian, atau buka chat riset AI tentangnya, dan pakai keduanya dengan aman.
requires: [ui.ticket.btn-ai-summarize, ui.ticket.btn-ai-research]
updated: 2026-10-28
---
Dua tombol di halaman tiket memakai AI untuk membantu Anda: `AI Summarize` dan `Ask AI (Research)`. Keduanya alat bantu berpikir. Keduanya tidak mengubah tiket.

## Siapa yang menggunakan

Role yang oleh administrator diberi izin `AI Summarize Ticket` dan `Ask AI (Research) on Ticket`, misalnya konsultan, module lead, dan para Head. `Ask AI (Research)` juga dibatasi pada tiket yang Anda tangani: Anda harus menjadi ticket lead atau anggota tim, kecuali Anda administrator atau role yang menyetujui di Ticket Validation.

## Sebelum Anda mulai

- Jangan pernah memasukkan kredensial, password, atau data pribadi customer ke alat AI. Tiket dapat memuat informasi kredensial customer: jangan menyalinnya ke pertanyaan.
- AI bisa salah. Periksa hasilnya terhadap tiket dan sistem sebelum Anda mengirim apa pun ke customer.
- Anda tidak dapat memilih model. Administrator mengaturnya di `AI Settings`.

## Langkah demi langkah

### Ringkas sebuah tiket

1. Buka tiketnya. Di panel kanan pilih `AI Summarize`. Panel itu tersembunyi pada jendela yang sempit, jadi lebarkan jendela atau pakai layar yang lebih besar bila Anda tidak melihatnya.
2. Sebuah jendela terbuka dengan tiga kartu yang terisi saat teks mengalir: `Issue`, `Resolution Steps` dan `Conclusion`. Di bawah `Resolution Steps`, `Documentation sources` mencantumkan halaman luar yang benar-benar dibuka AI.
3. Baca tiap kartu dan bandingkan dengan percakapannya.
4. Pilih `Copy` untuk membawa ringkasan sebagai Markdown biasa, untuk pesan chat, email, atau work log.
5. Tutup jendela dengan tanda silang.

Bila jendela menampilkan pemberitahuan kuning, jawaban mencapai batas panjangnya. Jawaban tetap ditampilkan dan sebagian besar berguna, tetapi tidak disimpan untuk dipakai ulang. Bila muncul kotak merah, ringkasan gagal: tutup jendela dan coba lagi nanti.

### Tanya AI tentang tiket

1. Di panel yang sama pilih `Ask AI (Research)`.
2. Sebuah chat riset terbuka untuk tiket ini. Tulis pertanyaan Anda dengan kata biasa, misalnya cara menangani sebuah pesan galat, lalu baca jawaban dan sumbernya.
3. Pakai yang Anda pelajari dalam balasan atau `Internal Note` Anda sendiri. Jangan menempelkan jawaban AI ke customer tanpa memeriksanya.

## Apa yang terjadi selanjutnya

Tidak ada tombol yang mengubah tiket. Apa yang Anda lakukan dengan jawabannya, seperti balasan atau catatan, terserah Anda. Pemakaian dihitung di `AI Usage`, yang dipantau administrator untuk biaya.

## Pertanyaan umum

**Saya tidak melihat tombolnya.** Role Anda tidak punya izinnya. Untuk `Ask AI (Research)` Anda juga harus menjadi ticket lead atau anggota tim. Tanyakan kepada administrator atau ticket lead.

**Ringkasan berubah saat saya membukanya lagi.** Ringkasan disimpan sebentar, jadi tiket yang tidak berubah menampilkan ringkasan yang sama dengan cepat. Saat tiket berubah, misalnya ada pesan baru, status baru, atau anggota baru, ringkasan baru dibuat. Pakai `Copy` bila Anda perlu menyimpannya.

<!-- sources: resources/views/ticket/show.blade.php, app/Support/TicketTeamAccess.php, app/Services/Ai/AiTicketSummaryService.php -->

---
key: ai-tools
chapter: my-workspace
order: 130
title: AI Assistant dan AI Research
summary: Tanyakan data EcoSystem Anda dengan AI Assistant, atau cari informasi di web dengan AI Research.
requires: [ai-assistant, ai-research]
updated: 2026-10-27
---
Bagian `AI Tools` memiliki dua halaman obrolan. `AI Assistant` menjawab pertanyaan tentang data di EcoSystem. `AI Research` mencari informasi di web publik. Pilih yang sesuai dengan pertanyaan Anda.

## Siapa yang menggunakan

Employee yang rolenya mencakup `AI Assistant`, `AI Research`, atau keduanya. Anda hanya melihat halaman yang menjadi akses Anda.

## Sebelum Anda mulai

| | AI Assistant | AI Research |
|---|---|---|
| Sumber | Data EcoSystem Anda (Ticket, SLA, Project delivery, dan lainnya) | Hanya web publik |
| Riwayat tersimpan | Tidak, obrolan tidak disimpan | Ya, percakapan tersimpan di daftar `History` |
| Lampiran | Hingga 5 berkas, masing-masing 10 MB | PDF dan gambar, sesuai batas yang tertera di halaman |

AI Assistant menghormati akses Anda. Bila Anda tidak berhak atas sebagian data, ia menyatakannya dan tidak menampilkannya.

> [!WARNING]
> Jawaban AI dapat keliru. Selalu verifikasi informasi penting sebelum Anda bertindak. Jangan menempelkan data rahasia ke AI Research, karena ia bekerja dengan web publik.

## Langkah demi langkah

### Bertanya kepada AI Assistant

1. Buka `AI Assistant` dari sidebar. Halamannya bernama `EcoSystem Assistant`.
2. Ketik pertanyaan Anda di kotak (hingga 4.000 karakter) lalu kirim. Teks panjang yang Anda tempel menjadi lampiran yang dapat dibuka dari obrolan.
3. Untuk menambah berkas, pilih tombol lampirkan. Anda dapat melampirkan gambar PNG, JPEG, GIF, atau WEBP, berkas PDF, serta berkas teks atau kode. Jenis berkas lain dilewati dan balasan menyebutkan yang mana.
4. Baca jawaban yang muncul bertahap. Gunakan tombol salin di bawah balasan untuk menyalinnya.
5. Pilih `Clear conversation` untuk mulai dari awal. Percakapan hanya disimpan sebentar dan tidak pernah disimpan sebagai catatan.

### Mencari di web dengan AI Research

1. Buka `AI Research` dari sidebar. Halamannya bernama `Research Assistant`.
2. Ketik apa yang ingin dicari lalu kirim. Sumber yang dipakai untuk sebuah balasan dicantumkan bersamanya.
3. Pilih `Limits & behaviour` di bagian bawah halaman untuk membaca batas rinci panjang pesan, lampiran per pesan, dan per percakapan.
4. Jika jawaban panjang berhenti di tengah, pilih `Continue` di bawahnya untuk melanjutkan dari titik berhenti.
5. Gunakan panel `History` untuk membuka kembali percakapan tersimpan, atau `Start a new chat` untuk memulai yang baru.

## Apa yang terjadi selanjutnya

AI Assistant melupakan obrolan setelah beberapa waktu, jadi salin apa pun yang ingin Anda simpan. AI Research menyimpan percakapan Anda di `History` sehingga dapat Anda buka lagi.

## Pertanyaan umum

**Asisten menyatakan tidak dapat menampilkan sebagian data.** Role Anda tidak memiliki akses ke data itu. Lihat [[access-and-menus]].

**Alat mana untuk pertanyaan tentang Ticket kami?** Gunakan AI Assistant. AI Research tidak dapat melihat data EcoSystem.

**Sebuah berkas diabaikan.** Hanya jenis berkas yang didukung yang dapat dibaca. Balasan mencantumkan berkas yang dilewati.

## Topik terkait

- [[navigating-ecosystem]]
- [[access-and-menus]]

<!-- sources: routes/web.php, app/Http/Controllers/AiAssistantController.php, app/Http/Controllers/AiResearchController.php, app/Services/Ai/AiChatService.php, resources/views/ai/assistant.blade.php, resources/views/ai/research.blade.php, resources/views/partials/sidebar.blade.php -->

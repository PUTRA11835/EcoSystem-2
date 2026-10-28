---
key: ai-settings
chapter: administration
order: 132
title: AI Settings
summary: Pilih model AI dan tingkat berpikir di balik setiap asisten, dan pahami biaya tiap pilihan.
requires: [control-center.ai-settings]
updated: 2026-10-28
---
AI Settings adalah satu-satunya tempat administrator menentukan model AI yang menjalankan setiap asisten di EcoSystem. Pengguna tidak pernah memilih model sendiri: semua orang mendapat model yang diatur di sini.

## Siapa yang menggunakan

Administrator, dan role mana pun yang diberi menu `Control Center` › `AI Settings` di Menu Access.

## Sebelum Anda mulai

- Model yang Anda pilih menentukan penyedia mana yang ditagih dan harga per token. Model yang lebih kuat lebih mahal pada setiap pesan.
- Perubahan berlaku pada pesan berikutnya juga langsung. Tidak ada deployment dan tidak ada cara membatalkan untuk pesan yang sudah terkirim.
- Baca dulu `Control Center` › `AI Usage` bila Anda ingin melihat berapa biaya pilihan saat ini.

## Langkah demi langkah

![Halaman AI Settings dengan satu kartu per asisten, masing-masing dengan daftar model dan effort](images/guide/ai-settings.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Control Center` › `AI Settings`. Setiap asisten punya kartu sendiri:
   - `AI Research`: mencari informasi di web. Satu jawaban juga dapat memicu pencarian web yang ditagih.
   - `AI Assistant`: menjawab hanya dari data EcoSystem, memakai alat lokal.
   - `Ticket Analyzer`: satu analisis terstruktur per tiket yang ditinjau di Ticket Validation.
   - `Ticket Q&A (Staging Validation)`: tanya jawab saat memvalidasi tiket.
   - `AI Summarize (Detail Tiket)`: ringkasan di halaman detail tiket.
   - `Word Report Generator (Struktur & Data)` dan `Word Report Generator (Susun Dokumen)`: dua fase laporan Word. Fase kedua paling berat, jadi punya model sendiri.
2. Di `Model`, pilih sebuah model. Harga per 1 juta token (masukan dan keluaran) tampil di samping nama. Model Claude dan GPT sama-sama ditawarkan, dan penyedia mengikuti modelnya.
3. Di `Thinking / Reasoning Effort`, pilih berapa lama model berpikir. Effort yang lebih tinggi berarti berpikir lebih dalam dan memakai lebih banyak token. Daftarnya bergantung pada model: model Claude menawarkan `Off`, `Low`, `Medium`, `High`, `Xhigh` dan `Max`, dan model GPT menawarkan `Off`, `Minimal`, `Low`, `Medium` dan `High`.
4. Pilih `Save settings`. Halaman mengonfirmasi bahwa chat baru memakai pengaturan itu langsung. `Cancel` membiarkan semuanya seperti semula.

### Mengapa sebagian model tidak ada di sebuah kartu

Asisten yang harus mencari di web (`AI Research`, `Ticket Analyzer`, `Ticket Q&A`, `AI Summarize`, dan fase laporan Word) hanya menampilkan model yang punya pencarian web bawaan. `AI Assistant` bekerja hanya pada data EcoSystem, jadi ia juga menawarkan `Claude Haiku 4.5` yang lebih kecil.

## Apa yang terjadi selanjutnya

Pesan berikutnya dari setiap asisten memakai model baru. Setiap penyimpanan tercatat di `Audit Log` pada modul `AI Settings`, dengan pengaturan sebelum dan sesudahnya, sehingga Anda dapat menelusuri siapa yang mengubah pilihan yang memengaruhi biaya.

## Pertanyaan umum

**Saya tidak melihat model yang saya harapkan.** Kartu hanya menampilkan model yang cocok untuk asisten itu, seperti dijelaskan di atas.

**Biaya naik setelah saya mengganti model.** Periksa `AI Usage` untuk model dan fiturnya, lalu pindahkan asisten ke model yang lebih murah atau effort yang lebih rendah.

**Seorang pengguna meminta memilih model sendiri.** Itu tidak mungkin, memang disengaja. Ubah asistennya di sini.

**Apakah perubahan memengaruhi percakapan yang sedang berjalan?** Pesan berikutnya memakai pengaturan baru. Pesan sebelumnya tetap seperti semula.

<!-- sources: routes/web.php, app/Http/Controllers/AiSettingsController.php, app/Support/AiModelSettings.php, resources/views/admin/ai-settings.blade.php -->

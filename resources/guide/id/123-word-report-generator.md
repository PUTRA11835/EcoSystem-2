---
key: word-report-generator
chapter: reporting
order: 123
title: Word Report Generator
summary: Buat laporan Word dari template customer dan data nyata EcoSystem dengan menjelaskan kebutuhan Anda lewat chat.
requires: [word-report-generator]
updated: 2026-10-28
---
Word Report Generator membuat laporan yang mempertahankan tata letak template Word dan mengisinya dengan data nyata dari EcoSystem. Anda memilih template, menjelaskan laporan lewat chat, lalu mengunduh hasilnya.

## Siapa yang menggunakan

Administrator, dan role mana pun yang diberi menu `Word Report Generator` oleh administrator.

## Sebelum Anda mulai

- Template tidak diunggah di halaman ini. Template tersimpan di pustaka template tiap customer, pada tab `Report Templates` di halaman detail customer. Minta pemilik customer itu menambahkan template yang Anda butuhkan.
- Laporan memakai data nyata. Periksa siapa yang boleh menerima berkasnya sebelum Anda membagikannya.
- Jangan pernah memasukkan kredensial atau data pribadi ke chat.

## Langkah demi langkah

1. Buka `Word Report Generator`. Halaman ini punya tiga bagian: `History` di kiri, pratinjau (`Pratinjau Laporan`) di tengah, dan `Chat` di kanan.
2. Di `Chat`, buka `Pilih Template` dan pilih template-nya. Baris `Template` menunjukkan yang terpilih.
3. Tulis kebutuhan Anda, misalnya customer dan periodenya, lalu pilih `Send`.
4. Ikuti progresnya di pratinjau: `Baca Struktur` (membaca template), `Ambil Data` (mengumpulkan data) dan `Susun Dokumen` (menyusun berkas).
5. Saat laporan siap, periksa di pratinjau dan unduh sebagai `.docx` atau `.pdf`.

### Bila proses berhenti

- Bila asisten butuh keterangan lebih (`AI butuh klarifikasi`), jawab pertanyaannya di chat agar proses berlanjut.
- Bila proses dijeda karena layanan AI sedang sibuk (`Dijeda sementara`), progres yang selesai tetap tersimpan. Lanjutkan saat tombolnya muncul.
- Bila gagal (`Gagal generate laporan`), pakai tombol coba lagi yang muncul, atau kirim instruksi yang lebih jelas.

### Pakai laporan sebelumnya

`History` menampilkan laporan yang pernah Anda buat. Pilih salah satu untuk membukanya lagi di pratinjau.

## Apa yang terjadi selanjutnya

Berkas mengikuti tata letak template Anda, dengan data yang sudah terisi. Laporan tetap ada di `History` agar dapat dibuka dan diunduh lagi.

## Pertanyaan umum

**Pratinjau menulis belum ada laporan dipilih.** Pilih template dan kirim instruksi lebih dulu, atau pilih item di `History`.

**Tidak ada template untuk dipilih.** Customer belum punya template di tab `Report Templates`.

**Laporan memuat angka yang salah.** Sebutkan apa yang salah di chat agar dapat disusun ulang, dan bandingkan dengan laporan sumbernya.

<!-- sources: routes/web.php, app/Http/Controllers/ReportGeneratorController.php, resources/views/reports/generate.blade.php -->

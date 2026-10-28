---
key: my-letter-requests
chapter: my-workspace
order: 120
title: My Letter Requests
summary: Minta surat kepada HR seperti surat keterangan kerja, pantau permintaannya, dan unduh surat yang sudah jadi.
requires: [general.my-letter-requests]
updated: 2026-10-27
---
Gunakan `My Letter Requests` untuk meminta surat kepada HR: surat keterangan kerja, surat referensi, atau surat lain yang Anda butuhkan. Setelah HR menyetujui, Anda mengunduh suratnya di sini dan surat juga dikirim ke email Anda.

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `My Letter Requests`.

## Sebelum Anda mulai

Ketahui surat apa yang Anda butuhkan, untuk apa, dan batas tanggal kebutuhannya.

## Langkah demi langkah

### Meminta surat

1. Buka `My Letter Requests` dari sidebar.
2. Pilih `Request a Letter`. Jika tombolnya tidak terlihat, role Anda tidak dapat membuat permintaan; hubungi administrator.
3. Pilih `Letter` dari daftar. Jika perusahaan mengizinkan, Anda juga dapat memilih `Other — a letter not on this list`, lalu jelaskan di `Which letter do you need?` (HR menulisnya sebagai surat khusus).
4. Pilih `Language` surat. Bawaannya mengikuti jenis surat.
5. Isi `Purpose`, misalnya pengajuan visa atau pinjaman bank.
6. Pilih `Needed by` bila ada batas waktu. Tanggalnya tidak boleh yang sudah lewat.
7. Tambahkan `Notes for HR` bila perlu.
8. Pilih `Send Request`.

### Memantau permintaan

1. Pada tabel `My Requests`, baca `Letter`, `Purpose`, `Asked on`, `Needed by`, dan `Status`.
2. `Status` berupa `Pending`, `In Progress`, `Done`, `Rejected`, atau `Cancelled`. Untuk permintaan yang ditolak, alasannya ditampilkan.

### Mengubah atau membatalkan permintaan

1. Permintaan dapat diubah atau dibatalkan sampai HR mulai mengerjakannya, yaitu selama masih `Pending`.
2. Gunakan ikon edit untuk membuka `Change Request`, perbarui, lalu simpan.
3. Gunakan ikon batal (`Cancel the request`) dan konfirmasi untuk menariknya.

### Mengunduh surat

1. Saat permintaan `Done` (tampil `Approved — ready` bila hanya suratnya yang sudah siap), pilih `Download the letter`.
2. Surat yang diterbitkan HR tentang Anda dan dikirim kepada Anda juga tercantum di `Letters About Me`, masing-masing dengan tombol `Download`.

## Apa yang terjadi selanjutnya

HR menerima permintaan Anda, mengerjakannya (`In Progress`), lalu menyelesaikannya. Surat yang sudah jadi dikirim ke email Anda saat email terkirim. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Surat yang saya butuhkan tidak ada di daftar.** Gunakan `Other — a letter not on this list` bila tersedia, atau tanyakan langsung kepada HR.

**Saya tidak dapat mengedit permintaan.** HR sudah mulai mengerjakannya. Hubungi HR.

## Topik terkait

- [[statuses-and-approvals]]
- [[my-profile]]
- [[access-and-menus]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyLetterRequestController.php, resources/views/hr-general/letters/my-requests.blade.php, app/Models/Letters/LetterRequest.php -->

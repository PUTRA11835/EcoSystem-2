---
key: offering-letter
chapter: human-capital
order: 40
title: Offering Letter
summary: Tulis, tandatangani, dan kirim Offering Letter, lalu buat Employee saat penawaran diterima.
requires: [general.offering-letter, general.recruitment.offers, general.recruitment.offers.settings]
updated: 2026-10-27
---
`Offering Letter` mengubah kandidat di tahap penawaran menjadi surat bertanda tangan, mengirimkannya, dan mencatat keputusannya. Saat diterima, akun Employee dibuat.

## Siapa yang menggunakan

Staf HR yang menyiapkan penawaran dan mengelola komponen kompensasi.

## Sebelum Anda mulai

- Buka `Human Capital` › `Offering Letter`. Ada dua tab: `Offering Letter Table` dan `Offering Settings`.
- Penandatangan harus Employee yang tanda tangannya sudah tersimpan di profilnya. Tanpa itu, surat tidak dapat ditandatangani.
- Atur dahulu penomoran dan komponen kompensasi di `Offering Settings` (lihat di bawah).

## Langkah demi langkah

### Menulis surat

1. Di `Offering Letter Table`, pilih `Add Offering Letter`.
2. Pilih `Letter Language`, lalu periksa `Offer Date` dan `Letter No.`.
3. Pilih kandidat di `Candidate Data`. Hanya kandidat di tahap `Offer` yang ditampilkan, dan memilihnya akan mengisi nama, email, telepon, dan posisi. Anda juga dapat mengisi data secara manual.
4. Isi `Job Description`, `Benefits`, dan `Start Date`. Tanggal mulai menjadi tanggal bergabung saat penawaran diterima.
5. Di `Compensation`, pilih `Salary Type` (gross atau nett) dan masukkan nilai untuk tiap komponen. `Total Compensation` dan `Base Salary Percentage` diperbarui saat Anda mengetik.
6. Pilih `Signatory`, tambahkan `Notes`, lalu pilih `Save`.

> [!NOTE]
> Gaji pokok di bawah persentase minimum diberi tanda saat Anda menulis, tetapi tidak menghalangi penyimpanan.

### Menandatangani, mengirim, dan mencatat keputusan

1. Pilih aksi tanda tangan pada baris untuk menandatangani dengan tanda tangan tersimpan milik penandatangan. Status menjadi `Signed`.
2. Unduh PDF untuk memeriksanya.
3. Pilih `Send to candidate`, periksa `Subject` dan `Message`, centang konfirmasi, lalu kirim. Status menjadi `Sent`.
4. Ketika kandidat menjawab, pilih `Candidate accepted` atau `Candidate rejected`.

Statusnya adalah `Draft`, `Signed`, `Sent`, `Accepted`, dan `Rejected`. Hanya surat yang sudah ditandatangani yang dapat dikirim.

### Menerima dan membuat Employee

1. Di `Offer Accepted — Create Employee`, isi `Employee ID (ECI)`, `Full Name`, `Login Email`, `Home Base`, dan `Default Password` (minimal 8 karakter).
2. Periksa `Join Date`, yang diisi dari tanggal mulai pada surat.
3. Pilih `Accept & Create Employee`.

Kata sandi awal dikirim lewat email kepada kandidat bersama nama penggunanya; kata sandi itu hanya berlaku sampai mereka membuat kata sandi sendiri.

### Offering Settings

Atur `Letter Number Format`, jumlah digit nomor urut, `Signing City`, `Days to Respond`, dan persentase gaji pokok minimum. Tambahkan komponen kompensasi beserta nama Inggrisnya, apakah dihitung sebagai gaji pokok atau tunjangan, dan nilai bawaan (opsional).

## Apa yang terjadi selanjutnya

Employee baru muncul di [[employee-data]] dan di [[onboarding]], dan kandidat berstatus `Hired`.

## Pertanyaan umum

**Mengapa saya tidak bisa menandatangani?** Pilih penandatangan dari daftar Employee dan pastikan ia memiliki tanda tangan di profilnya.

**Bagaimana bila penandatangan belum punya tanda tangan?** Sistem memberi tahu bahwa tanda tangan belum ada. Minta orang tersebut menambahkannya di profilnya, lalu tandatangani suratnya.

## Topik terkait

- [[recruitment]]
- [[onboarding]]
- [[contracts]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/offering/index.blade.php, resources/views/hr-general/offering/components/offer-modal.blade.php, resources/views/hr-general/offering/settings.blade.php, app/Http/Controllers/HR_General/RecruitmentOfferController.php, app/Models/Recruitment/Offer.php -->

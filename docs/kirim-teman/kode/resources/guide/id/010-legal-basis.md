---
key: legal-basis
chapter: business-legal
order: 10
title: Legal Basis
summary: Kelola daftar undang-undang dan peraturan di balik aturan payroll, BPJS, lembur, kontrak, dan offboarding.
requires: [legal, finance.legal-basis, finance.legal-basis.settings]
updated: 2026-10-27
---
Legal Basis adalah daftar (register) undang-undang dan peraturan di balik aturan perhitungan di EcoSystem: PPh 21, BPJS, Overtime, Contract (PKWT), dan Offboarding. Untuk setiap aturan, register mencatat peraturannya, tanggal mulai berlaku, perubahan terakhir, dan apakah sudah ada yang meninjaunya. Register ini mendokumentasikan dasar hukum; ia tidak mengubah rumus atau nominal di [[payroll]].

## Siapa yang menggunakan

Peninjau dari Legal, HR, dan Finance, serta administrator. Halaman ini memiliki tiga tab: `Dashboard`, `Register`, dan `Settings`. Anda hanya melihat tab yang diizinkan untuk Role Anda.

## Sebelum Anda mulai

- Tentukan siapa yang meninjau aturan. Menandai aturan sebagai `Reviewed` berarti penanggung jawab sudah memeriksanya.

## Langkah demi langkah

### Membaca register

1. Buka `Business & Legal` › `Legal Basis` › `Register`.
2. Saring berdasarkan modul bila Anda hanya ingin satu bidang, misalnya BPJS.
3. Baca kolom `Regulation`, `In force since`, `Last amended`, `Status`, dan `Confidence`.
4. Gunakan tautan sumber, bila ada, untuk membuka teks resminya.

Setiap entri memiliki salah satu dari tiga status: `Reviewed`, `Needs review`, atau `Superseded` (digantikan versi yang lebih baru dan tidak lagi dipakai untuk perhitungan).

### Meninjau entri

1. Cocokkan aturan dengan sumber resminya.
2. Klik `Mark reviewed` pada entri. Untuk menerima semua aturan satu modul sekaligus, gunakan `Accept review (all)` pada modul tersebut.
3. Klik `Needs review` untuk mengembalikan entri agar diperiksa lagi.

### Menambah atau memperbarui aturan

1. Klik `Add rule or new version`.
2. Isi kunci aturan, `Module`, `Regulation type`, `Number`, tahun, `Title / subject`, `Article(s)`, `In force since`, dan `Confidence` Anda. Tambahkan tautan sumber resmi dan catatan bila ada.
3. Simpan. Entri ditambahkan dengan status `Needs review`.

Tanggal berlaku baru membuat versi baru. Versi lama tetap ada di riwayat. Gunakan `Supersede` untuk menghentikan sebuah versi dan `Restore` untuk mengembalikannya sebagai `Needs review`.

> [!IMPORTANT]
> Mengubah entri yang sudah ada akan mengembalikannya ke `Needs review`, karena tinjauan sebelumnya berlaku untuk isi lama. Kolom data perhitungan memengaruhi perhitungan yang membaca aturan itu, jadi ubah hanya dengan persetujuan Legal atau Finance.

### Memilih cara modul memakai register (Settings)

1. Buka tab `Settings`.
2. Gunakan `Show legal basis information across the app` untuk menampilkan atau menyembunyikan panel dan peringatan dasar hukum di seluruh aplikasi.
3. Untuk setiap modul, centang `Show panel` dan `Warn` sesuai kebutuhan, lalu pilih mode: `Draft mode` (modul berjalan sekarang dan ditandai draft) atau `Enforced` (modul diblokir sampai aturannya `Reviewed`). Modul Contract tidak memiliki mode enforced.
4. Klik `Save switches`. Perubahan langsung berlaku.

> [!WARNING]
> `Enforced` dapat menghentikan perhitungan payroll atau fitur lain sampai aturan yang tercantum selesai ditinjau. Periksa dulu kolom aturan yang tertunda.

## Apa yang terjadi selanjutnya

Bila diaktifkan, halaman lain seperti Payroll menampilkan panel dasar hukum atau peringatan tentang aturan yang belum ditinjau.

## Pertanyaan umum

**Apakah mengubah register mengubah nominal payroll?** Tidak. Payroll membaca pengaturannya sendiri. Register hanya mencatat dasar hukumnya.

**Mengapa saya hanya melihat sebagian tab?** Setiap tab memiliki akses tersendiri.

## Topik terkait

- [[payroll]]
- [[bpjs]]
- [[pph21]]
- [[contracts]]
- [[offboarding]]

<!-- sources: routes/hr-offboarding.php, routes/web.php, app/Http/Controllers/Finance/LegalBasisController.php, app/Http/Controllers/LegalDashboardController.php, app/Services/Legal/LegalBasisService.php, resources/views/legal/*.blade.php, resources/views/finance/legal-basis/settings.blade.php -->

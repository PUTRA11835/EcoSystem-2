---
key: external-contracts
chapter: external
order: 20
title: External Contract
summary: Siapkan Contract Consultant sebagai Draft, lengkapi data untuk mengaktifkannya, dan cetak dokumennya.
requires: [general.external.contracts]
updated: 2026-10-27
---
`External Contract` adalah tempat HR menyiapkan Contract Consultant eksternal: ketentuan penugasan, tarif, penempatan kerja, Assignment, dan penandatangan, hingga menjadi dokumen yang dapat dicetak.

## Siapa yang menggunakan

Staf HR yang mengelola Contract Consultant. Menambah, mengubah, dan menghapus memerlukan izin yang sesuai; tarif memerlukan izin tersendiri.

## Sebelum Anda mulai

- Buka `External` › `External Contract`. Ada dua tab, `Contracts` dan `Settings`.
- Tambahkan Consultant terlebih dahulu dan lengkapi profilnya, lihat [[external-consultants]].
- Jenis Contract, jenis posisi, dan penempatan kerja yang ditawarkan pada formulir dikelola di tab `Settings`, lihat [[external-contract-settings]].

## Draft dan Active

Anda dapat menyiapkan Contract sebagai `Draft` kapan saja. Contract hanya dapat diubah menjadi `Active` bila data Consultant sudah lengkap. Pada daftar `Status`, `Active` bertanda `(needs complete consultant data)` sampai data lengkap. Naskah Contract dibekukan setelah `Active`. `Expired` mengikuti tanggal berakhir secara otomatis, dan hanya Draft yang dapat dihapus.

Kartu `Contract readiness` pada halaman Consultant menampilkan persentase dan apa yang `Still missing`. Consultant diperiksa pada: nomor KTP (NIK), nomor ponsel, email pribadi, alamat domisili beserta kota dan provinsi, nama bank, nomor rekening, nama pemilik rekening, serta dokumen KTP dan Kartu Keluarga. Klik item yang kurang untuk membuka tab yang tepat di tab baru, sehingga formulir Anda tidak hilang.

## Langkah demi langkah

### Memulai Contract

1. Buka `Contracts` lalu pilih `Add External Contract`.
2. Di `External consultant`, cari dan pilih orangnya, lalu konfirmasi. Anda dibawa ke formulir untuk Consultant tersebut.

Halaman yang sama juga dapat dicapai dari profil Consultant dengan `Manage External Contract`.

### Mengisi formulir

1. Periksa `Consultant summary` dan `Contract readiness` di bagian atas.
2. Di `New external contract`, biarkan `Template` otomatis untuk memakai Template jenis posisi, atau Template eksternal bawaan. Atau pilih salah satu.
3. Periksa `Contract number` yang dibuat otomatis; ubah hanya bila perlu.
4. Pilih `Contract type`, yang menentukan cara Consultant dibayar. `Billing unit` mengikutinya, dan dapat Anda ubah.
5. Atur `Status` ke `Draft`, atau `Active` bila diizinkan.
6. Pilih `Position type` dan isi `Position / role`.
7. Isi `Start`, `End` (opsional), dan `Signing date`.
8. Isi `Rate` dan `Currency`. Kolom tarif tersembunyi bila Anda tidak punya izin khusus tarif; menyimpan tidak pernah mengubah tarif yang tidak dapat Anda lihat. Administrator dapat memberikan izinnya.
9. Isi `Work volume`, `Hours per manday`, `Monthly proration`, dan `Payment term (days)`.
10. Pilih `Work placement`. Cabang dari daftar cabang ditawarkan, ditambah tempat lain yang ditambahkan di Settings, atau pilih `Other (type it)…` lalu tulis sendiri.
11. Isi `Assignment` (Project atau lingkup pekerjaan). Jika dikosongkan, isinya mengikuti penugasan Consultant.
12. Pilih `Company signatory`.
13. Tambahkan `Notes` untuk keperluan internal.
14. Buat naskah, baca di editor, dan sesuaikan bila perlu. Pilih `Save contract`.

> [!NOTE]
> Hanya penandatangan yang ditetapkan yang dapat dipilih sebagai penandatangan. Jika penandatangan yang diwajibkan belum tersedia, sebuah peringatan menjelaskannya; minta administrator mendaftarkan orang tersebut sebagai penandatangan.

### Mengubah, mencetak, atau menghapus

Di `Contract history`, setiap Contract menampilkan nomor, jenis, periode, tarif, dan statusnya. Gunakan `View` untuk membaca dokumen, `Print` untuk mencetak atau menyimpan sebagai PDF, `Edit` untuk mengubah formulir, dan `Delete` untuk menghapus Draft.

## Apa yang terjadi selanjutnya

Contract `Active` menentukan bulan-bulan yang dapat diisi Consultant pada Timesheet, lihat [[external-timesheet-payment]].

## Pertanyaan umum

**Mengapa saya tidak bisa memilih Active?** Data Consultant belum lengkap. Perbaiki dahulu item yang tercantum.

**Mengapa tarif tampil sebagai titik-titik?** Anda tidak punya izin untuk melihat tarif.

## Topik terkait

- [[external-consultants]]
- [[external-contract-settings]]
- [[external-timesheet-payment]]
- [[external-letters]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/contracts/index.blade.php, resources/views/hr-general/external/contracts/manage.blade.php, resources/views/hr-general/external/components/signer-warning.blade.php, config/hc_contract.php, config/hc_external.php, app/Http/Controllers/HR_General/ExternalContractController.php -->

---
key: external-contract-settings
chapter: external
order: 30
title: Pengaturan External Contract
summary: Kelola jenis Contract, jenis posisi, dan penempatan kerja yang ditawarkan pada formulir External Contract.
requires: [general.external.contracts.settings]
updated: 2026-10-27
---
`External Contract Settings` mengatur pilihan dropdown pada formulir External Contract. Rapikan pilihan ini agar HR selalu memilih opsi yang tepat.

## Siapa yang menggunakan

Staf HR atau administrator yang merawat opsi External Contract.

## Sebelum Anda mulai

Buka `External` › `External Contract` lalu pilih tab `Settings`. Tombol untuk menambah, mengubah, mengurutkan, atau menghapus hanya tampil bila Anda punya izin yang sesuai.

Halaman ini memiliki empat daftar: `Contract types`, `Position types`, `Branches`, dan `Other work placements`.

## Langkah demi langkah

### Jenis Contract

`Contract types` menentukan cara Consultant dibayar. Ketentuan Contract (satuan tarif, jam per manday, proration) mengikuti jenis bawaan.

1. Pilih `Add contract type`.
2. Isi `Name`, misalnya `Probation`.
3. Di `Behaves like`, pilih jenis bawaan yang ditiru: penomoran, aturan tanggal, dan Template-nya sama.
4. Tambahkan `Description` opsional sebagai petunjuk bagi pemilih, lalu pilih `Add contract type`.

### Jenis posisi

`Position types` menggambarkan jenis posisi yang dituju sebuah Contract, misalnya Consultant. Template yang ditulis untuk suatu jenis posisi dipilih otomatis untuk Contract berjenis itu.

1. Pilih `Add position type`.
2. Isi nama lalu pilih `Add position type`.
3. Anda juga dapat menambah beberapa sekaligus lewat opsi massal.

### Work placement

`Branches` mendaftar semua cabang aktif dari `Management` › `Branches`. Cabang otomatis ditawarkan sebagai penempatan kerja. Untuk menambah, mengganti nama, atau mematikan cabang, ubah di daftar cabang.

Untuk tempat yang bukan cabang, seperti Client site atau Remote:

1. Di `Other work placements`, pilih `Add work placement`.
2. Isi `Name` dan `Description` opsional, lalu tambahkan.
3. Tandai satu penempatan sebagai bawaan dengan bintang agar terpilih otomatis pada Contract baru.

### Mengurutkan, mengganti nama, menyembunyikan, atau menghapus

Pada setiap daftar Anda dapat:

- Memindahkan opsi lebih awal atau lebih akhir pada formulir dengan tombol panah.
- Mengganti nama dan mengubah deskripsinya.
- Mematikan `On form` untuk menyembunyikan opsi tanpa menghapusnya.
- Mengatur opsi `Default`.
- Menghapus opsi. Daftar menampilkan `In use, cannot be deleted` bila masih dipakai Contract atau Template, dan jenis bawaan tidak dapat dihapus.

Kolom `Used by` menunjukkan berapa banyak Contract dan Template yang memakai suatu opsi.

## Apa yang terjadi selanjutnya

Pilihan langsung muncul pada formulir External Contract, lihat [[external-contracts]]. Daftar serupa untuk Contract internal ada di tab `Settings` pada menu Contract, lihat [[contracts]].

## Pertanyaan umum

**Mengapa saya tidak bisa menghapus sebuah jenis?** Jenis itu sedang dipakai atau bawaan. Sembunyikan dengan `On form`.

**Mengapa sebuah cabang tidak ada di Work placement?** Hanya cabang aktif yang ditawarkan.

## Topik terkait

- [[external-contracts]]
- [[contracts]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/contracts/settings.blade.php, resources/views/hr-general/contracts/components/settings-options.blade.php, app/Http/Controllers/HR_General/ContractSettingController.php, app/Services/Contracts/ContractOptionService.php -->

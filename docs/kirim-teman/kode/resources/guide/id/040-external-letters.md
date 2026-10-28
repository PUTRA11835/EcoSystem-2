---
key: external-letters
chapter: external
order: 40
title: Surat Consultant
summary: Buat dan arsipkan Assignment Letter, Employment Certificate, dan Reference Letter untuk Consultant.
requires: [general.external.assignment-letters, general.external.employment-certificates, general.external.reference-letters]
updated: 2026-10-27
---
Tiga halaman di bagian `External` membuat surat PDF untuk Consultant: `Assignment Letter`, `Employment Certificate`, dan `Reference Letter`. Setiap surat diarsipkan sebagaimana diterbitkan.

## Siapa yang menggunakan

Staf HR yang menerbitkan surat untuk Consultant eksternal. Setiap halaman memiliki akses sendiri, jadi Anda mungkin hanya melihat sebagian. Tombol `Create Document` hanya tampil bila Anda punya izin Create.

## Sebelum Anda mulai

- Buka `External` lalu pilih `Assignment Letter`, `Employment Certificate`, atau `Reference Letter`.
- Consultant harus sudah ada, lihat [[external-consultants]]. Jika surat merujuk pada penugasan, buat Contract terlebih dahulu, lihat [[external-contracts]].
- Penandatangan harus terdaftar sebagai penandatangan. Hanya penandatangan yang ditetapkan yang ditawarkan; bila ada yang belum tersedia, halaman menampilkan peringatan. Minta administrator mendaftarkan orang tersebut.

## Langkah demi langkah

### Membaca daftar

1. Buka salah satu dari tiga halaman. Kartu menghitung `All external documents` dan surat tiap jenis.
2. Cari berdasarkan Consultant, ID Consultant, nomor surat, atau Contract.
3. Baca tabel: `Document`, `Consultant`, `External contract`, `Issued`, dan `Status`.
4. Gunakan tombol PDF untuk membuka atau mengunduh surat.

Daftar mencatat bahwa setiap surat yang dibuat otomatis diarsipkan dan didaftarkan sebagai surat keluar. Anda juga dapat menemukannya di register, lihat [[letter-templates]].

### Membuat surat

1. Pilih `Create Document`.
2. Di `Document info`, pilih `Document type` (sudah terpilih sesuai halaman yang Anda buka), `Document date`, dan `Language`.
3. Biarkan `Reference no.` kosong untuk penomoran otomatis dari urutan surat keluar, atau ketik sendiri.
4. Isi kepada siapa surat ditujukan (`Addressed to`) dan tujuannya. Kolom tujuan berbeda tiap jenis: `Assignment / purpose`, `Purpose`, atau `Reference summary`.
5. Di `Consultant data`, cari `External consultant` dan, bila perlu, pilih `Related EXT contract`. Pilih Contract bila surat harus merujuk pada periode penugasan yang aktif.
6. Di `Assignment & authorization`, isi `Assignment location`, `Assignment start`, dan `Assignment end`.
7. Pilih `Signer`. Jabatan penandatangan terisi, dan Anda dapat mengubah apa yang tercetak di bawah tanda tangan.
8. Tambahkan `Archive note` (opsional).
9. Pilih `Generate & Archive PDF`.

PDF dibuat dan ditambahkan ke daftar dengan nomor surat dan status.

> [!IMPORTANT]
> Periksa detailnya sebelum membuat surat. Surat yang sudah diterbitkan diarsipkan sebagaimana diterbitkan.

## Apa yang terjadi selanjutnya

Surat muncul di daftar jenisnya dan di `Letter Register` sebagai surat keluar. Buka PDF untuk mencetak atau mengirimkannya.

## Pertanyaan umum

**Bisakah surat ini dipakai untuk Employee internal?** Tidak. Surat Employee dibuat di `Letter Templates`.

**Mengapa daftar penandatangan pendek?** Hanya penandatangan yang ditetapkan yang dapat menandatangani surat Consultant.

## Topik terkait

- [[external-consultants]]
- [[external-contracts]]
- [[letter-templates]]

<!-- sources: routes/hr-external.php, app/Support/External/ExternalRules.php, config/hc_external.php, resources/views/hr-general/external/documents/index.blade.php, resources/views/hr-general/external/documents/components/list-table.blade.php, resources/views/hr-general/external/components/signer-warning.blade.php -->

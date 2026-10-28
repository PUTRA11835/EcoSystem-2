---
key: letter-templates
chapter: human-capital
order: 60
title: Letter Templates
summary: Tangani permintaan surat Employee, buat surat, kelola register, dan atur penomoran.
requires: [general.letters.dashboard, general.letters.requests, general.letters.register, general.letters.compose, general.letter-templates]
updated: 2026-10-27
---
`Letter Templates` adalah hub HR untuk surat perusahaan: menerima permintaan Employee, membuat surat dari Template, mencatatnya di register, dan menyimpan aturan penomoran.

## Siapa yang menggunakan

Staf HR yang menerbitkan surat seperti surat keterangan kerja, surat referensi, dan surat tugas. Employee meminta surat lewat My Letter Requests.

## Sebelum Anda mulai

Buka `Human Capital` › `Letter Templates`. Ada hingga lima tab: `Dashboard`, `Requests`, `Letter Register`, `Create Letter`, dan `Settings`. Setiap tab memiliki akses sendiri, jadi Anda mungkin hanya melihat sebagian.

Penandatangan dan kop surat (halaman tempat surat dicetak) dikelola di Document Identity. Surat hanya dapat ditandatangani oleh penandatangan terdaftar yang tanda tangannya tersimpan.

## Langkah demi langkah

### Memproses permintaan Employee

1. Buka `Requests`. Tabel `Employee Letter Requests` menampilkan Employee, surat, `Needed by`, dan `Status`.
2. Pilih aksi proses pada sebuah permintaan. Untuk jenis standar, surat dibuat dari Template-nya; untuk jenis lain, Anda mulai dari sebuah Template lalu menyesuaikan kalimatnya.
3. Periksa surat, lalu tandatangani dengan tanda tangan tersimpan milik penandatangan.
4. Pilih `Complete & Send` untuk mengirim surat bertanda tangan lewat email dan menyelesaikan permintaan.

Untuk menolak, pilih `Reject the request` dan isi `Reason (shown to the employee)`. Status permintaan adalah `Pending`, `In Progress`, `Done`, `Rejected`, dan `Cancelled`. Jika email gagal terkirim, gunakan `Resend the failed email`.

### Membuat surat sendiri

1. Buka `Create Letter`.
2. Pilih `Letter Language`, lalu `Letter Template`, atau mulai surat khusus dari Template mana pun di `Start from`.
3. Pilih `Employee`. Nama, posisi, departemen, dan tanggal bergabung terisi dari data Employee.
4. Isi kolom yang diminta Template tersebut, misalnya tujuan surat keterangan kerja.
5. Periksa `Letter Date`, `Letter Number` (kosong berarti dibuat otomatis), `Signer Role`, `Signer`, dan alamat `Send To (email)`.
6. Simpan untuk membuat surat.

### Menggunakan register

1. Buka `Letter Register`.
2. Beralih antara semua surat, surat masuk, dan surat keluar. Setiap surat menampilkan `Number`, `Date`, `To / From`, `Subject`, `Source`, `Code`, `Status`, dan `Delivery`.
3. Buka surat sebagai PDF, atau ubah detail pengirimannya dan unggah berkas akhir.
4. Untuk mencatat surat yang bukan dibuat dari sistem, gunakan tombol surat masuk atau surat keluar lalu isi detailnya.

Status surat adalah `Draft`, `Signed`, `Sent`, `Logged`, `Received`, dan `Void`. Membatalkan surat (Void) mempertahankan nomornya di register, lengkap dengan alasannya.

### Mengatur penomoran dan jenis surat

Di `Settings`, ikuti langkah-langkahnya: `Numbering & Codes` (format nomor surat keluar dan nomor agenda surat masuk, nama perusahaan, dan kota penandatanganan), `Letter Types` (kode dan bahasa bawaan), `Template Designs`, dan `Employee Requests` (apa saja yang boleh diminta Employee). `Signers` dan `Letterheads` tampil bagi yang punya akses ke Document Identity.

## Apa yang terjadi selanjutnya

Employee melihat status permintaannya di My Letter Requests. Tab `Dashboard` menampilkan permintaan yang menunggu HR dan jumlah surat yang diterbitkan tahun ini per jenis.

## Pertanyaan umum

**Mengapa Template tidak ada di Create Letter?** Template diaktifkan di `Settings`. Offering Letter dan Contract ditulis di menunya masing-masing.

**Ke mana surat untuk Consultant?** Surat itu dibuat di menu External, lihat [[external-letters]].

## Topik terkait

- [[external-letters]]
- [[offering-letter]]
- [[contracts]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/letters/requests.blade.php, resources/views/hr-general/letters/compose.blade.php, resources/views/hr-general/letters/register.blade.php, resources/views/hr-general/letters/settings.blade.php, app/Models/Letters/Letter.php, app/Models/Letters/LetterRequest.php -->

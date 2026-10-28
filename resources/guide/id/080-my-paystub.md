---
key: my-paystub
chapter: my-workspace
order: 80
title: Paystub
summary: Buka slip gaji Anda yang sudah dipublikasikan dan unduh sebagai PDF dalam bahasa Indonesia atau Inggris.
requires: [general.my-paystub]
updated: 2026-10-27
---
`Paystub` mencantumkan slip gaji yang sudah dipublikasikan Finance untuk Anda. Setiap slip dapat diunduh sebagai PDF.

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `Paystub`. Anda hanya melihat slip milik Anda sendiri.

## Sebelum Anda mulai

Slip gaji baru muncul setelah Finance mempublikasikannya untuk periode tersebut. Sebelum itu, halaman menampilkan `No payslip has been published for you yet.`

## Langkah demi langkah

### Membaca slip gaji

1. Buka `Paystub` dari sidebar.
2. Temukan periode di kolom pertama. Kolom ini menampilkan rentang tanggal dan kapan slip dipublikasikan.
3. Baca `Slip no.`, `Earnings`, `Deductions`, dan `Take-home pay`.

### Mengunduh slip gaji

1. Pada baris periode yang Anda butuhkan, buka kolom `Download`.
2. Pilih `Indonesian` atau `English` untuk menentukan bahasa PDF.
3. Simpan berkas saat peramban Anda menanyakannya.

Periode terbaru ditampilkan paling atas.

### Melihat rekap singkat di My Profile

`My Profile` › `Basic Payment` menampilkan rekap baca-saja berjudul `Recent Paystubs`. Riwayat lengkap dan unduhan PDF tetap ada di halaman `Paystub`.

## Apa yang terjadi selanjutnya

Tidak ada tindakan lanjutan. Jika ada angka yang tampak keliru, hubungi HR atau Finance dan sebutkan periode serta nomor slipnya.

## Pertanyaan umum

**Slip gaji saya tidak terdaftar.** Periodenya mungkin belum dipublikasikan, atau Anda bukan bagian dari proses Payroll tersebut. Tanyakan kepada Finance.

**Di My Profile muncul pesan bahwa Paystub tidak tersedia untuk role saya.** Role Anda tidak memiliki menu `Paystub`. Lihat [[access-and-menus]].

> [!NOTE]
> Slip gaji berisi data gaji pribadi. Unduh hanya di perangkat yang Anda percaya dan jangan bagikan berkasnya.

## Topik terkait

- [[my-profile]]
- [[access-and-menus]]

<!-- sources: routes/finance.php, app/Http/Controllers/Finance/MyPaystubController.php, resources/views/finance/payroll/my-paystub.blade.php, resources/views/master/employee/sections/payment-recap.blade.php -->

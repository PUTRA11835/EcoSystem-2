---
key: onboarding
chapter: human-capital
order: 20
title: Onboarding
summary: Pantau kelengkapan data induk tiap Employee, lalu verifikasi dan kunci profilnya.
requires: [general.onboarding]
updated: 2026-10-27
---
`Onboarding` menunjukkan seberapa lengkap data wajib setiap Employee dan memungkinkan HR memverifikasi serta mengunci profil setelah lengkap. Tidak ada yang diketik di halaman ini: progres mengikuti data yang disimpan di `Employee Data` dan di My Profile.

## Siapa yang menggunakan

Staf HR yang memeriksa Employee baru maupun lama. Employee melihat progres dirinya sendiri sebagai kartu `Data readiness` di My Profile.

## Sebelum Anda mulai

- Buka `Human Capital` › `Onboarding`.
- Mengunci dan membuka kunci adalah izin yang terpisah. Jika Anda tidak melihat `Verify & Lock`, `Lock all ready`, atau kotak unlock, minta kepada administrator.

## Langkah demi langkah

### Membaca ikhtisar

1. Periksa `Onboarding Summary` di sisi kiri: persentase, `Employees`, `In progress`, `Complete`, dan `Items left`.
2. Di tabel `Employee Onboarding`, baca `Join date`, `Status`, `Type`, dan `Progress`. Label kuning di bawah bilah menunjukkan kelompok yang masih kurang item (misalnya `Payroll · 2`).
3. Gunakan ikon corong untuk memfilter berdasarkan status kunci (`Ready to lock`, `Locked`), `Type`, progres, dan kelompok yang kurang item.

Item wajib dibagi menjadi kelompok `Profile`, `Payroll`, `BPJS`, dan `Contract`. Employee internal diperiksa pada data pribadi, kontak, nomor KTP, NPWP, rekening bank, nomor BPJS, Contract aktif, dan tanggal bergabung. Consultant eksternal hanya diperiksa pada kontak, alamat, KTP, NPWP, dan rekening bank.

### Membuka checklist

1. Pilih `Open Checklist` pada sebuah baris.
2. Baca `Checklist`. Setiap item berstatus `Complete` atau `Missing`, disertai petunjuk cara mengisinya.
3. Pilih `Fill in` pada item yang kurang. Profil Employee terbuka pada tab yang tepat. Item Contract dan tanggal bergabung diisi oleh HR.

### Memverifikasi dan mengunci profil

1. Buka checklist Employee ketika semua item `Complete`. Kartu `Profile lock` menampilkan `Ready to lock`.
2. Pilih `Verify & Lock` lalu konfirmasi.
3. Status berubah menjadi `Locked`. Employee tidak lagi dapat mengubah `Basic Data`, `Address`, `Identification`, `Bank Account`, atau `HR Profile`; bagian itu tampil sebagai View Only di My Profile. HR tetap dapat mengubahnya.

Untuk mengunci banyak profil sekaligus, pilih `Lock all ready` di daftar. Semua profil yang sudah lengkap dalam filter aktif akan dikunci setelah Anda mengonfirmasi.

### Membuka kunci profil

1. Buka checklist Employee yang terkunci.
2. Di `Profile lock`, ketik alasan pada `Reason for unlocking (required)`. Alasan minimal 5 karakter.
3. Pilih `Unlock profile`.

> [!WARNING]
> Setelah dibuka, Employee dapat mengubah bagian-bagian tersebut lagi. Kunci kembali profil setelah perubahan selesai.

### Alat Join date

Bila filter item yang kurang diatur ke tanggal bergabung, tampil alat opsional untuk mengetik tanggal bergabung beberapa orang sekaligus, mengimpor daftar pasangan ECI dan tanggal (pratinjau dahulu, lalu `Apply`), atau mengirim pengingat.

## Apa yang terjadi selanjutnya

Profil yang terkunci menampilkan lencana `Locked` di `Employee Data`. Data yang lengkap juga memungkinkan Contract diubah menjadi Active, lihat [[contracts]].

## Pertanyaan umum

**Mengapa saya tidak bisa mengunci?** Tombol `Verify & Lock` nonaktif sampai semua item wajib lengkap.

**Siapa yang dapat melihat progres saya?** Anda melihat progres sendiri di My Profile; HR melihat semua progres di sini.

## Topik terkait

- [[employee-data]]
- [[contracts]]
- [[offboarding]]

<!-- sources: routes/hr-general.php, config/hc_onboarding.php, resources/views/hr-general/onboarding/index.blade.php, resources/views/hr-general/onboarding/show.blade.php, resources/views/hr-general/onboarding/components/join-date-tools.blade.php, resources/views/master/employee/show.blade.php -->

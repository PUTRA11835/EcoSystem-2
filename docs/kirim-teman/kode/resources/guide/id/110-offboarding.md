---
key: offboarding
chapter: human-capital
order: 110
title: Offboarding
summary: Mulai Offboarding, kerjakan checklist, hitung penyelesaian akhir, nonaktifkan akses, lalu selesaikan.
requires: [general.offboarding, general.offboarding.list, general.offboarding.settings]
updated: 2026-10-27
---
`Offboarding` memandu HR saat seseorang keluar: checklist tugas, penyelesaian akhir untuk Employee, penonaktifan akses pada tanggal efektif, dan penyelesaian yang menutup Contract.

## Siapa yang menggunakan

Staf HR. Halaman ini memiliki dua tab, `Offboardings` dan `Settings`; Anda melihat tab yang diizinkan untuk Role Anda.

## Sebelum Anda mulai

- Buka `Human Capital` › `Offboarding`.
- Jika spanduk menyebut `Offboarding is switched off`, Offboarding baru tidak dapat dimulai sampai diaktifkan di `Settings`. Catatan yang sudah ada tetap terlihat.
- Checklist dibuat dari Template tugas di `Settings`.

## Langkah demi langkah

### Memulai Offboarding

1. Pilih `Start Offboarding`.
2. Pilih `Employee or consultant` dan `Offboarding type`. Untuk Employee: `Resignation`, `End of PKWT`, `Retirement`, `Termination (PHK)`, `Mutual agreement`, atau `Death`. Untuk Consultant: `End of engagement`, `Termination (PHK)`, atau `Mutual agreement`.
3. Isi `Reason for the termination`, `Effective date`, `Last working day`, dan `Notes`.
4. Pilih `Start & open`.

Statusnya `In progress`. Halaman menampilkan empat langkah: checklist, penyelesaian, akses, selesai.

### Mengerjakan checklist

1. Di `Offboarding Checklist`, tandai setiap tugas sebagai `Done` dan tambahkan catatan singkat.
2. Pantau `Offboarding Progress`: tugas yang selesai, dan tugas yang terlambat bila ada.

### Menghitung penyelesaian akhir

Khusus Employee. Consultant tidak memiliki penyelesaian akhir; penutupan penugasan, aset, dan akses ditangani lewat checklist.

1. Di `Final Settlement Calculation`, periksa `Offboarding type`, `Contract type`, dan `Effective date`.
2. Isi `Offboarding reason (PHK code)`, `Last working day`, `Paid leave balance (days)`, `Separation pay`, `Other compensation` dan, untuk pensiun, perkiraan manfaat pensiun.
3. Biarkan `Wage base (override)` kosong untuk memakai gaji pokok Contract ditambah tunjangan.
4. Pilih `Calculate & Save`.

Kalkulator dapat dimatikan, atau terblokir sampai entri dasar hukumnya ditinjau. Kedua kasus dijelaskan di halaman, lengkap dengan tautan ke `Settings` atau ke register dasar hukum. Lihat [[legal-basis]].

### Menonaktifkan akses

1. Pilih `Deactivate access`. Tombol ini tersedia pada tanggal efektif, dan setelah tugas lain selesai bila aturan itu aktif.
2. Konfirmasi. Orang tersebut tidak dapat lagi masuk; Role dan riwayatnya tetap tersimpan.

Jika `Deactivate access automatically` aktif di `Settings`, hal ini terjadi sendiri pada tanggal efektif.

Untuk kasus mendesak, `Settings` memiliki `Allow force deactivate`. Bila aktif dan akses belum dapat dinonaktifkan secara normal, muncul kotak untuk mengisi alasan (minimal 5 karakter, tersimpan di riwayat Employee). Pilih `Force deactivate` untuk menonaktifkan sebelum tanggalnya dan tanpa menunggu tugas lain.

> [!WARNING]
> Menonaktifkan akses langsung menghentikan orang tersebut untuk masuk. Periksa tanggal efektif dan checklist sebelum Anda mengonfirmasi.

### Menyelesaikan atau membatalkan

1. Bila semua tugas selesai dan akses sudah dinonaktifkan, pilih `Complete`. Contract yang berjalan ditutup sebagai `Terminated`.
2. Untuk menghentikan Offboarding yang aksesnya belum dinonaktifkan, gunakan `Cancel offboarding` dan isi alasan.

### Settings

`Settings` memiliki saklar `Offboarding`, `Final settlement calculator`, `Deactivate access automatically`, `Include external consultants`, `Require the other tasks first`, dan `Allow force deactivate`. Di sini juga diatur hari pengingat dan pembagi cuti, serta Anda dapat menambah, mengubah, atau menghapus tugas checklist (`Add task`). Perubahan tugas tidak memengaruhi Offboarding yang sudah dimulai.

## Apa yang terjadi selanjutnya

Offboarding yang selesai tetap tersimpan sebagai catatan. Employee tersebut tidak lagi masuk ke aplikasi.

## Pertanyaan umum

**Mengapa Deactivate access berwarna abu-abu?** Tombol itu terbuka pada tanggal efektif. Ia juga menunggu tugas lain bila aturan itu aktif.

## Topik terkait

- [[contracts]]
- [[employee-data]]
- [[inventory-assets]]

<!-- sources: routes/hr-offboarding.php, resources/views/hr-general/offboarding/index.blade.php, resources/views/hr-general/offboarding/show.blade.php, resources/views/hr-general/offboarding/settings.blade.php, app/Services/Offboarding/OffboardingService.php, app/Support/Offboarding -->

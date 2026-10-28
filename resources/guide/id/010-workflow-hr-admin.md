---
key: workflow-hr-admin
chapter: reference
order: 10
title: Alur kerja HR admin
summary: Jalur umum data pegawai dalam pekerjaan HR, dari bergabung hingga penggajian dan keluar.
requires: [general.onboarding, general.contracts, finance.payroll]
updated: 2026-10-27
---
Halaman ini menunjukkan bagaimana tugas-tugas utama HR saling terhubung, sesuai urutan yang biasa terjadi. Setiap langkah ditautkan ke topik dengan petunjuk rincinya.

## Siapa yang menggunakan

Administrator HR dan orang yang membantu mereka. Anda melihat langkah untuk menu yang dapat Anda buka.

## Jalur sekilas

| Tahap | Lokasi | Yang Anda lakukan | Rincian |
|---|---|---|---|
| 1. Merekrut | `Human Capital` › `Recruitment`, `Offering Letter` | Jalankan proses seleksi dan kirim penawaran | [[recruitment]], [[offering-letter]] |
| 2. Mencatat | `Human Capital` › `Employee` | Buat data pegawai dan lengkapi isinya | [[employee-data]] |
| 3. Onboarding | `Human Capital` › `Onboarding` | Pantau progres, lalu verifikasi dan kunci data | [[onboarding]] |
| 4. Contract | `Business & Legal` › `Contract` | Siapkan, aktifkan, dan tandatangani kontrak | [[contracts]] |
| 5. Kerja harian | `Human Capital` › `Attendance`, `Leave & Permit`, `Overtime` | Tinjau koreksi presensi, cuti, dan lembur | [[attendance-management]], [[leave-permit-management]], [[overtime-management]] |
| 6. Penggajian | `Finance` › `Payroll`, `BPJS`, `PPh 21` | Hitung, buat slip, dan publikasikan | [[payroll]], [[bpjs]], [[pph21]] |
| 7. Keluar | `Human Capital` › `Offboarding` | Jalankan proses keluar | [[offboarding]] |

## Langkah demi langkah

1. Lengkapi data pegawai terlebih dahulu. Contract tidak dapat diaktifkan selama data wajib belum lengkap, dan `Onboarding` menunjukkan apa yang masih terbuka.
2. Di `Onboarding`, periksa progres, lalu gunakan `Verify & Lock`. Profil yang terkunci tampil sebagai `View Only` bagi pegawai. HR dapat membukanya kembali, dan alasan wajib diisi.
3. Siapkan Contract dari data pegawai. Draft bersifat fleksibel; aktivasi memerlukan data yang lengkap.
4. Sepanjang bulan, selesaikan koreksi presensi, permintaan cuti, dan lembur agar tuntas sebelum Payroll.
5. Di `Payroll`, kerjakan periodenya: `Calculate`, lalu `Generate Slip`, lalu `Publish Payslip`. Jika opsi otomatis di `Settings` Payroll aktif, menyelesaikan `Calculate` juga memberi nomor dan mempublikasikan slip, sehingga pegawai langsung melihatnya di `Paystub`.
6. Saat seseorang keluar, mulai `Offboarding`.

> [!IMPORTANT]
> Payroll menampilkan di `Settings` apakah statusnya `Switched on`. Jika mati, minta orang yang berwenang menyalakannya sebelum Anda memulai periode.

## Apa yang terjadi selanjutnya

Pegawai melihat data baru, Contract, dan slip mereka di workspace masing-masing. Lihat [[workflow-employee]] untuk sisi pegawai.

## Pertanyaan umum

**Dari mana saya mulai untuk pegawai baru?** Dari `Employee`, lalu `Onboarding`.

**Ada langkah yang tidak ada di sidebar saya.** Setiap menu memiliki aksesnya sendiri. Lihat [[access-and-menus]].

## Topik terkait

- [[workflow-finance]]
- [[workflow-employee]]
- [[approval-workflows]]
- [[faq]]

<!-- sources: resources/views/partials/sidebar.blade.php, resources/views/finance/payroll/settings.blade.php, docs/HRGA/05-LOG-SESI.md -->

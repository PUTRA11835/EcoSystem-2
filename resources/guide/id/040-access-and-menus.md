---
key: access-and-menus
chapter: getting-started
order: 40
title: Akses dan menu
summary: Mengapa menu Anda berbeda dari rekan kerja, dan langkah bila ada menu atau tombol yang tidak terlihat.
requires: []
updated: 2026-10-27
---
Dua orang di perusahaan yang sama sering melihat menu EcoSystem yang berbeda. Ini memang disengaja: menu mengikuti akses Anda. Topik ini menjelaskan cara kerjanya dan apa yang perlu dilakukan bila ada yang Anda butuhkan tetapi tidak terlihat.

## Siapa yang menggunakan

Semua orang. Administrator yang mengelola akses dapat memakainya sebagai ringkasan dalam bahasa sederhana.

## Bagaimana menu Anda ditentukan

- **Role dan Menu Access.** Administrator memberi setiap Role daftar halaman dan tab. Untuk masing-masing, Role dapat memiliki `View` (membuka halaman) dan, bila relevan, `Create`, `Edit`, dan `Delete`. Anda dapat memegang lebih dari satu role.
- **ESS Settings.** Item layanan mandiri di bawah `My Workspace` (seperti `My Attendance`, `Overtime`, atau `Paystub`) juga dapat dinyalakan atau dimatikan untuk semua orang oleh administrator.
- **Menjadi Approver.** Jika Anda ditunjuk sebagai Approver pada rantai persetujuan mana pun, `My Approvals` muncul untuk Anda walau Anda tidak memiliki menu HR lain. Anda hanya melihat dokumen yang menyangkut Anda.
- **Jenis pegawai.** Consultant External melihat kumpulan menu layanan mandiri yang lebih ringkas. Halaman mereka dijelaskan di bab External.
- **Tab My Profile.** Setiap tab `My Profile` juga mengikuti aksesnya sendiri. Tab bertanda `View Only` dapat dibaca tetapi tidak dapat diubah. HR juga dapat mengunci profil yang sudah diverifikasi, sehingga tab utama menjadi `View Only` sampai HR membukanya kembali.

## Apa yang dapat dan tidak dapat dilakukan di sebuah halaman

Tombol mengikuti aturan yang sama. Jika Anda dapat membuka halaman tetapi tidak melihat `Create`, `Edit`, `Delete`, `Approve`, atau `Export`, role Anda memiliki `View` tetapi tidak memiliki aksi tersebut.

## Langkah demi langkah

### Bila menu tidak terlihat

1. Muat ulang halaman dan periksa kotak `Search menu...` di sidebar. Menunya mungkin terlipat di dalam sebuah bagian.
2. Cari topiknya di `User Guide`. Panduan hanya menampilkan topik untuk menu yang dapat Anda buka. Jika topiknya juga tidak ada, berarti menu itu tidak termasuk akses Anda.
3. Minta akses kepada HR atau administrator Anda. Sebutkan nama menu yang tepat dan alasan Anda membutuhkannya.

### Bila tombol tidak terlihat

1. Pastikan Request masih berada pada keadaan yang mengizinkan aksi tersebut. Misalnya, `Cancel` hanya tampil selama belum ada yang memproses Request.
2. Periksa apakah halaman bertanda `View Only`.
3. Jika Anda tetap membutuhkan aksi itu, minta administrator menambahkan izinnya ke role Anda.

### Bila item layanan mandiri yang Anda harapkan tidak ada

Item layanan mandiri dapat dimatikan untuk seluruh perusahaan di `ESS Settings`. Tanyakan kepada HR apakah fitur itu dipakai di perusahaan Anda.

> [!NOTE]
> Anda tidak dapat mengubah akses Anda sendiri. Role dan Menu Access dikelola oleh administrator.

## Apa yang terjadi selanjutnya

Setelah administrator mengubah akses Anda, keluar lalu masuk kembali bila menunya belum juga muncul.

## Pertanyaan umum

**Rekan saya melihat `Payroll` dan saya tidak.** `Payroll` adalah menu Finance. Akses mengikuti role, bukan nama departemen.

**Saya melihat sebuah menu tetapi halamannya menyatakan saya tidak punya akses.** Halaman atau salah satu tabnya tidak termasuk dalam role Anda. Hubungi administrator.

## Topik terkait

- [[navigating-ecosystem]]
- [[my-profile]]
- [[my-approvals]]
- [[welcome]]

<!-- sources: resources/views/partials/sidebar.blade.php, resources/views/management/roles/menu-access.blade.php, app/Http/Controllers/Management/EssSettingsController.php, config/external_ess.php, app/Http/Controllers/ProfileController.php, resources/views/master/employee/show.blade.php, app/Services/Approval/ApprovalEngine.php -->

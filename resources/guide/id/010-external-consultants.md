---
key: external-consultants
chapter: external
order: 10
title: External Consultant
summary: Tambah Consultant eksternal, cari mereka, dan kelola profil serta penugasannya.
requires: [general.external.consultants]
updated: 2026-10-27
---
`External Consultants` mendaftar Consultant eksternal perusahaan, lengkap dengan penugasan, vendor, skema, dan periodenya. Dari sini Anda menambah Consultant dan membuka profilnya untuk melengkapi data.

## Siapa yang menggunakan

HR dan administrator yang mengelola Consultant eksternal. Tombol dan tab yang Anda lihat bergantung pada akses Anda.

## Sebelum Anda mulai

- Buka `External` › `External Consultants`.
- Tombol `Add consultant` hanya tampil bila Anda punya izin Create. Jika tidak melihatnya, minta akses kepada administrator.
- Tentukan dahulu ID Consultant (ECI) sebelum mulai. ID diketik manual, tidak dibuat otomatis.

## Langkah demi langkah

### Mencari Consultant

1. Klik kartu ringkasan untuk memfilter: `Total consultants`, `Active`, `Mandays`, `Monthly`, atau `Other / not set`.
2. Ketik di kotak pencarian: nama, ID Consultant, vendor, klien, atau penugasan.
3. Baca kolom `Consultant`, `Vendor / Assignment`, `Scheme`, `Period`, `Rate`, dan `Status`. Kolom `Rate` hanya tampil bila Anda punya izin melihat tarif.
4. Pilih `Refresh` untuk memuat ulang daftar.

Periode menampilkan `No period`, `Upcoming`, `In period`, atau `Ended`, berdasarkan tanggal penugasan.

### Menambah Consultant

1. Pilih `Add consultant`.
2. Ketik `Consultant ID (ECI)`. Panel di samping menampilkan urutan penomoran agar Anda dapat memilih nomor yang kosong: ID kosong berikutnya, ID terakhir yang dipakai, dan nomor yang belum terpakai di antaranya. Pilih `Use next` untuk mengisi ID kosong berikutnya. Formatnya huruf E, dua digit tahun, lalu tiga digit nomor, misalnya E26060.
3. Isi `Work email`, `First name`, dan `Nick name`. Kolom ini wajib, begitu juga `Password` dan `Confirm password` (minimal 6 karakter).
4. Isi `Last name`, `Mobile phone`, `Title`, `Gender`, `Religion`, `Marital status`, `Birth date`, dan `Birth place` bila perlu. Anda juga dapat mengisinya nanti.
5. Pilih `Create consultant`.

Sistem memeriksa bahwa ID dan nick name belum dipakai. Setelah itu Anda melanjutkan di halaman profil Consultant.

### Melengkapi profil

1. Pilih Consultant untuk membuka ringkasan, lalu pilih `Edit consultant`.
2. Kerjakan tab yang tampil untuk Anda. Consultant memiliki tab lebih sedikit daripada Employee internal: `Basic Data`, `Address`, `Identification`, `Education`, `Qualification`, `Bank Account`, dan `Attachment`. Tidak ada tab keluarga, Contract, atau pembayaran.
3. Di `Basic Data`, isi blok `Engagement`: `Engagement scheme`, `Vendor / Partner`, `Client / Principal company`, `Assignment / Role`, `Managed by`, `Start date`, dan `End date`. Kolom `Rate` dan `Currency` hanya tampil bagi yang punya izin tarif. Pilih `Save engagement`.
4. Kartu `Emergency Contact` di `Basic Data` (`Name`, `Relationship`, `Phone`) bersifat opsional bagi Consultant. Kartu ini tidak menghalangi Contract.

Akses ke setiap tab diberikan terpisah dari tab untuk staf HR, jadi Anda mungkin hanya melihat sebagian.

> [!IMPORTANT]
> Data KTP, NPWP, dan rekening bank diperlukan sebelum Contract dapat diubah menjadi Active. Lihat [[external-contracts]].

### Membuka halaman terkait

Di halaman ringkasan, `Manage External Contract` membuka Contract Consultant ini. `Quick Actions` menautkan ke surat Consultant yang boleh Anda gunakan, lihat [[external-letters]]. Ringkasan juga menampilkan `Identity & Biodata`, `Contact & Address`, `Payment Account`, dan `Documents & Attachments`.

## Apa yang terjadi selanjutnya

Data penugasan dibaca oleh [[external-contracts]] dan [[external-timesheet-payment]]. Consultant juga diperiksa di [[onboarding]] pada item kontak, KTP, NPWP, dan rekening bank.

## Pertanyaan umum

**Mengapa tarif tidak terlihat?** Tarif bersifat sensitif dan memerlukan izin tersendiri.

**Bisakah ECI diubah nanti?** Hubungi administrator. ECI juga menjadi nama pengguna Consultant untuk masuk.

## Topik terkait

- [[external-contracts]]
- [[external-timesheet-payment]]
- [[external-letters]]
- [[onboarding]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/consultants/index.blade.php, resources/views/hr-general/external/consultants/show.blade.php, resources/views/hr-general/external/consultants/components/list-table.blade.php, resources/views/master/employee/sections/hrprofile.blade.php, resources/views/master/employee/sections/emergency-card.blade.php, resources/views/master/employee/show.blade.php, app/Http/Controllers/HR_General/ExternalConsultantController.php -->

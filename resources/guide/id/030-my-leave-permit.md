---
key: my-leave-permit
chapter: my-workspace
order: 30
title: My Leave & Permit
summary: Periksa kuota cuti dan izin, ajukan cuti, serta pantau atau ubah pengajuan Anda.
requires: [ess.my_leave_permit]
updated: 2026-10-27
---
`My Leave & Permit` menampilkan sisa kuota cuti dan izin Anda, memungkinkan Anda mengajukannya, dan menyimpan riwayat pengajuan.

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `My Leave & Permit`. HR memeriksa pengajuannya.

## Sebelum Anda mulai

- Ketahui jenis cuti, tanggal, dan alasannya.
- Sebagian jenis memerlukan lampiran, seperti surat dokter. Siapkan berkas PDF, JPG, PNG, atau DOC maksimal 1 MB.

## Langkah demi langkah

### Memeriksa kuota

1. Buka `My Leave & Permit` dari sidebar.
2. Pilih `Year` di bagian atas bila Anda membutuhkan tahun lain.
3. Baca kartu `Total Quota Allocated`, `Quota Used (Approved)`, `Pending Approvals`, dan `Remaining Quota`.
4. Baca tabelnya. Untuk setiap `Leave / Permit Type` tampil `Category`, `Quota Rule` (`Annual Quota`, `Resets Monthly`, atau `Event-based`), serta `Allocated`, `Used`, `Pending`, dan `Remaining`.

Anda hanya melihat jenis cuti yang tersedia bagi Anda, misalnya jenis yang berlaku sesuai jenis kelamin Anda.

### Mengajukan cuti atau izin

1. Pilih `Apply Leave / Permit`.
2. Pilih `Leave / Permit Type`. Petunjuk menampilkan sisa kuota Anda untuk jenis itu.
3. Pilih `Full Day` atau `Half-Day Leave (0.5 Day)`. Untuk setengah hari, nyatakan apakah Anda tetap bekerja via WFH atau tidak pada sisa hari tersebut.
4. Pilih `Start Date` dan `End Date`. Anda tidak dapat memilih tanggal yang sudah lewat.
5. Tulis `Reason`.
6. Unggah `Attachment` bila jenisnya mewajibkan. Formulir menandainya sebagai wajib.
7. Pilih tombol kirim.

> [!IMPORTANT]
> Formulir menahan pengajuan bila jumlah hari yang diminta melebihi sisa kuota Anda untuk jenis tersebut.

### Memantau pengajuan

1. Gulir ke riwayat pengajuan Anda.
2. Saring berdasarkan status dengan daftar `All Statuses`.
3. Baca `Status`: `Pending`, `Approved`, `Ask for Edit`, atau `Rejected`.
4. Pilih `View Details` untuk melihat alasan, catatan HR, dan log aktivitas.

### Mengubah pengajuan

1. Temukan pengajuan berstatus `Pending` atau `Ask for Edit`.
2. Pilih `Edit`, ubah datanya, lalu simpan. Judulnya menampilkan nomor pengajuan.

## Apa yang terjadi selanjutnya

HR memeriksa pengajuan Anda. `Approved` memakai kuota Anda. `Ask for Edit` berarti HR membutuhkan perubahan: baca `HR Notes / Feedback`, ubah, lalu kirim ulang. `Rejected` menutup pengajuan; ajukan lagi bila perlu. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Jenis cuti yang saya harapkan tidak ada di daftar.** Jenis tersebut mungkin tidak aktif atau tidak tersedia untuk profil Anda. Tanyakan kepada HR.

**Mengapa saya tidak dapat mengirim?** Periksa tanggalnya, pastikan alasan terisi, lampiran di bawah 1 MB, dan kuota Anda cukup.

## Topik terkait

- [[statuses-and-approvals]]
- [[my-attendance]]
- [[my-calendar-timesheet]]

<!-- sources: routes/web.php, app/Http/Controllers/HR/LeavePermitController.php, resources/views/hr-general/leave-permit/my-leave-permit.blade.php, resources/views/hr-general/leave-permit/components/modal-apply.blade.php, resources/views/hr-general/leave-permit/components/modal-review.blade.php -->

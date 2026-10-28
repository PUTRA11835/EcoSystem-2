---
key: leave-permit-management
chapter: human-capital
order: 80
title: Pengelolaan Leave & Permit
summary: Tinjau pengajuan cuti dan izin, kelola jenis cuti dan kuota, serta baca laporan.
requires: [hr_general.leave_permit]
updated: 2026-10-27
---
`Leave & Permit` adalah halaman HR untuk meninjau pengajuan, mengelola jenis cuti dan kuota, serta membaca laporan. Employee mengajukan lewat My Leave & Permit.

## Siapa yang menggunakan

Staf HR dan administrator yang menangani cuti dan izin.

## Sebelum Anda mulai

Buka `Human Capital` › `Leave & Permit`. Halaman ini dapat memiliki empat tab, tergantung akses Anda: `Approval Inbox`, `Master Data Leave Type`, `All Employee Quotas`, dan `Reports & Analytics`. Penghitung di halaman menunjukkan berapa permintaan yang masih Pending.

## Langkah demi langkah

### Meninjau pengajuan

1. Buka `Approval Inbox`. Gunakan ikon corong pada `Employee`, `Type`, dan `Status` untuk menyaring daftar.
2. Periksa `Period`, `Total Days`, dan `Quota Limit Check`, yang menunjukkan apakah permintaan masih dalam sisa kuota.
3. Pilih pengajuan untuk membuka `Application Review Details`. Baca alasannya dan buka dokumen pendukung bila ada.
4. Ketik tanggapan Anda di `HR Notes / Feedback`. Kolom ini wajib diisi saat Anda menolak.
5. Pilih `Approve`, `Reject`, atau `Ask for Edit`. Dengan `Ask for Edit`, Employee diminta merevisi permintaannya.

Filter status menawarkan `All Statuses`, `Pending Only`, `Approved`, `Revision Requested`, dan `Rejected`. `Application Activity Log` menampilkan setiap langkahnya.

> [!NOTE]
> Bila Anda juga memiliki akses ubah, `Edit Details (HR Override)` memungkinkan Anda memperbaiki detail pengajuan.

### Mencatat cuti atas nama Employee

1. Pilih `Log Leave / Permit` di bagian atas halaman.
2. Pilih Employee dan jenis cutinya.
3. Masukkan tanggal. Jumlah hari ditampilkan sebagai pratinjau dan diperiksa terhadap kuota.
4. Kirim formulir.

### Mengelola jenis cuti

1. Buka `Master Data Leave Type`.
2. Pilih `Add New Leave Type`, atau ubah yang sudah ada.
3. Isi `Code`, `Category`, `Leave Type Name`, dan `Default Quota (Days)`. Atur `Min Service Period`, `Paid Status`, `Gender Target`, apakah lampiran berkas wajib, dan deskripsi.
4. Simpan. Setiap jenis dapat diubah antara `Active` dan `Nonactive`.

### Memeriksa kuota dan laporan

- `All Employee Quotas` mendaftar kuota setiap Employee. Filter berdasarkan nama atau ECI, lalu buka baris untuk melihat detail.
- `Reports & Analytics` menampilkan total seperti hari cuti disetujui yang sudah diambil serta permintaan yang ditolak atau direvisi, dengan tabel per Employee dan per jenis cuti.

Anda dapat memakai `Export` di halaman untuk mengunduh pengajuan.

## Apa yang terjadi selanjutnya

Employee melihat keputusannya di My Leave & Permit. Cuti yang disetujui tampil sebagai `Leave / Permit` pada rekap Attendance, lihat [[attendance-management]].

## Pertanyaan umum

**Mengapa saya tidak bisa menyetujui?** Anda memerlukan akses ubah pada tab Approval Inbox. Tanpa itu, Anda hanya dapat membaca.

**Apa arti peringatan kuota?** Jumlah hari yang diminta melebihi sisa saldo jenis cuti tersebut.

## Topik terkait

- [[attendance-management]]
- [[overtime-management]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/leave-permit/index.blade.php, resources/views/hr-general/leave-permit/components/modal-review.blade.php, resources/views/hr-general/leave-permit/components/modal-type-form.blade.php -->

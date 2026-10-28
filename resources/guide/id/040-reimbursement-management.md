---
key: reimbursement-management
chapter: finance
order: 40
title: Reimbursement Management
summary: Periksa, setujui, dan bayar Reimbursement Employee, serta atur aturan dan langkah persetujuannya.
requires: [general.reimbursement, general.settings.reimbursement, general.approval-workflow.reimbursement]
updated: 2026-10-27
---
Reimbursement Management adalah tempat HR, Finance, dan Approver menangani Request Reimbursement yang diajukan Employee. Halaman ini memiliki tiga tab: `Dashboard`, `Reimbursement Management`, dan `Settings`.

## Siapa yang menggunakan

Approver, HR, dan Finance. Anda hanya melihat tab yang diizinkan untuk Role Anda, dan setiap tombol hanya tampil bila Anda memiliki akses yang sesuai (buat, export, import, setujui, hapus). Bila ada yang tidak tampil, hubungi administrator.

## Sebelum Anda mulai

- Employee mengajukan sendiri di `My Workspace` › `Reimbursement` (lihat [[my-reimbursement]]).
- Langkah persetujuan harus sudah ada di `Settings` › `Approval Workflow`. Lihat [[approval-workflows]].

## Langkah demi langkah

### Mencari dokumen

1. Buka `Reimbursement Management` › `Reimbursement Management`.
2. Baca kartu: `Waiting for You`, `Pending Total`, `Approved`, `Awaiting Payment`, dan `Rejected`. Klik `Waiting for You` atau `Awaiting Payment` untuk menyaring daftar; klik lagi untuk menghapus saringan.
3. Pilih `Month`, atau gunakan ikon filter di header tabel untuk mencari berdasarkan dokumen, pemohon, Approver, atau status.
4. Baris yang perlu perhatian diberi sorotan. Klik `Reset Filter` untuk mengulang.

### Menyetujui atau menolak

1. Buka dokumen.
2. Periksa item, kuitansi, dan tautan pendukung di `Reimbursement Item Details`.
3. Bila giliran Anda, klik `Approve & Sign`, atau klik `Reject`, tulis alasan (minimal 5 karakter), lalu klik `Confirm Rejection`.
4. `Approval Trail` menampilkan siapa yang bertindak dan kapan.

### Mencatat pembayaran

1. Buka dokumen `Approved` yang berstatus `Awaiting payment`.
2. Di `Record payment`, isi `Paid on` dan `Transfer reference`. Tambahkan catatan bila perlu.
3. Klik `Mark as paid`. Dokumen menampilkan `Paid` beserta tanggalnya.

> [!WARNING]
> Bila Reimbursement sudah menjadi bagian dari periode payroll, halaman akan memberi peringatan. Reimbursement tersebut dibayar bersama gaji. Catat pembayaran di sini hanya bila memang sudah dibayar terpisah, agar tidak terbayar dua kali.

### Membuat, mengimpor, dan mengekspor

- `New RB` membuat dokumen atas nama Employee.
- `Import RB` memuat banyak dokumen dari berkas template.
- `Monthly Export` mengunduh daftar yang sudah disaring. Tanpa rentang tanggal, yang diekspor adalah bulan berjalan. Satu dokumen juga bisa diekspor ke Excel atau dicetak dengan `Print Form`.

### Menghapus dokumen

Buka dokumen, klik `Delete`, lalu isi alasan. Dokumen yang dihapus tetap terlihat pada filter status `Deleted`. Menghapus memerlukan akses khusus.

### Mengatur aturan (Settings)

1. Buka tab `Settings`.
2. Di `Approval Workflow`, tambah atau atur langkah, lalu klik `Save Workflow`.
3. Di `Reimbursement Rules`, atur batas seperti rentang pengajuan, usia kuitansi, jumlah item dan nominal per dokumen, serta apakah tautan pendukung atau nomor kuitansi wajib. Klik `Save Settings`.

Aturan berjenis flag hanya menyorot dokumen. Aturan berjenis block menolaknya. Nilai 0 berarti tanpa batas.

## Apa yang terjadi selanjutnya

Dokumen yang disetujui menunggu pembayaran. Bila [[payroll]] menyertakan Reimbursement yang disetujui, nilainya dibayar bersama gaji.

## Pertanyaan umum

**Mengapa saya tidak bisa menyetujui dokumen?** Hanya orang yang langkahnya sedang berjalan yang dapat bertindak. Halaman menampilkan langkah saat ini.

**Apakah perubahan aturan memengaruhi dokumen yang ada?** Perubahan aturan berlaku untuk pengajuan baru. Dokumen yang sedang berjalan tetap memakai langkahnya.

## Topik terkait

- [[my-reimbursement]]
- [[approval-workflows]]
- [[payroll]]
- [[my-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/ReimbursementController.php, app/Http/Controllers/HR_General/ReimbursementSettingController.php, resources/views/hr-general/reimbursement/*.blade.php, app/Models/Reimbursement/ReimbursementRequest.php -->

---
key: my-approvals
chapter: my-workspace
order: 100
title: My Approvals
summary: Lihat semua dokumen yang menunggu keputusan Anda dari seluruh modul, lalu setujui atau tolak.
requires: [general.approvals, "@approver"]
updated: 2026-10-27
---
`My Approvals` adalah kotak masuk keputusan Anda. Menu ini mengumpulkan dokumen Overtime, Reimbursement, Purchase Request, Cash Advance, dan Cash Advance Report yang menunggu Anda, sehingga Anda tidak perlu membuka setiap modul.

## Siapa yang menggunakan

Siapa pun yang ditunjuk sebagai Approver pada langkah persetujuan: kepala departemen, direktur, petugas Finance, atasan langsung, pemimpin Project, dan sebagainya. Anda tidak memerlukan menu HR untuk menyetujui. Menu ini muncul begitu Anda ditunjuk, dan Anda hanya melihat dokumen yang melibatkan Anda.

## Sebelum Anda mulai

Ketahui aturan modul yang Anda setujui. Baca seluruh dokumen, termasuk item dan lampiran, sebelum memutuskan.

## Langkah demi langkah

### Membuka kotak masuk

1. Pilih `My Approvals` di sidebar.
2. Baca kartunya. `Waiting for you` adalah totalnya, dan satu kartu per modul menampilkan jumlahnya masing-masing.
3. Pilih sebuah kartu untuk hanya menampilkan modul itu. Pilih `Waiting for you` untuk menampilkan semuanya lagi.

### Memeriksa dokumen

1. Pada tabel `Waiting for your decision`, baca `Module`, `Document No.`, `Requester`, `Your step`, dan `Waiting since`.
2. Pilih `Review` di kolom `Action`.
3. Baca rincian, daftar item, dan jejak persetujuan. Item yang ditandai perlu perhatian menjelaskan mengapa ditandai.

### Menyetujui

1. Pada dokumen, temukan kotak `Approval Actions`. Kotak ini menunjukkan langkah yang sedang berjalan.
2. Pilih `Approve` (tertulis `Approve & Sign` pada Reimbursement) dan konfirmasi bila diminta.

Pada Request Overtime, role Anda mungkin juga memungkinkan Anda menyesuaikan jam sebelum menyetujui. Bila Anda melakukannya, pemohon melihat bahwa jamnya disesuaikan.

### Menolak

1. Pilih `Reject`.
2. Isi alasan pada kotak catatan. Alasan wajib diisi, minimal 5 karakter pada sebagian besar dokumen.
3. Konfirmasikan penolakan dengan tombol di bawah kotak catatan (`Confirm Rejection` pada sebagian besar dokumen).

> [!WARNING]
> Penolakan mengakhiri seluruh dokumen. Langkah yang tersisa dilewati dan pemohon harus mengirim Request baru.

## Apa yang terjadi selanjutnya

Setelah Anda menyetujui, dokumen berpindah ke langkah berikutnya, atau menjadi `Approved` bila langkah Anda yang terakhir. Pemohon menerima Notification dalam kedua kasus. Dokumen keluar dari kotak masuk Anda. Jika kotak menyatakan dokumen menunggu langkah lain, berarti belum giliran Anda.

Sebagian jabatan juga diberi aksi tambahan seperti `Export`. Aksi ini hanya tampil bila role Anda mencakupnya.

## Pertanyaan umum

**Saya tidak melihat `My Approvals`.** Anda tidak ditunjuk sebagai Approver, dan role Anda tidak mencakup menunya. Tanyakan kepada pengelola alur persetujuan.

**Kotak masuk menyatakan `Nothing is waiting for you.`** Semua sudah Anda selesaikan.

**Saya menekan `Review` tetapi tidak dapat menyetujui.** Dokumen menunggu langkah lain, atau aturan administrator mencegah Anda bertindak.

## Topik terkait

- [[statuses-and-approvals]]
- [[my-overtime]]
- [[my-reimbursement]]
- [[navigating-ecosystem]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/ApprovalInboxController.php, app/Services/Approval/ApprovalEngine.php, resources/views/hr-general/approvals/index.blade.php, resources/views/hr-general/approvals/overtime.blade.php, resources/views/hr-general/reimbursement/show.blade.php, app/Services/Reimbursement/ReimbursementService.php, resources/views/home/command-center.blade.php -->

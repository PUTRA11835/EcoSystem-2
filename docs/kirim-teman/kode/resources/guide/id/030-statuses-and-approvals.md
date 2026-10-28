---
key: statuses-and-approvals
chapter: getting-started
order: 30
title: Status dan persetujuan
summary: Arti setiap status, alur sebuah Request melewati Approver, dan langkah setelah disetujui atau ditolak.
requires: []
updated: 2026-10-27
---
Sebagian besar Request di EcoSystem (Overtime, Reimbursement, Purchase Request, Cash Advance, Leave & Permit, dan lainnya) melewati satu atau beberapa Approver. Topik ini menjelaskan status yang Anda lihat dan langkah selanjutnya.

## Siapa yang menggunakan

Semua orang yang mengajukan Request, dan semua orang yang menjadi Approver.

## Alur sebuah Request

1. Anda mengirim Request. Dokumen mendapat nomor dan berstatus `Submitted`.
2. Approver pertama dalam rantai menerima Notification. Saat ia menyetujui, Request pindah ke langkah berikutnya dan tampil sebagai `In review`.
3. Saat langkah terakhir menyetujui, status menjadi `Approved`.
4. Jika ada Approver yang menolak, Request langsung menjadi `Rejected`. Langkah yang tersisa dilewati dan Anda menerima Notification berisi alasannya.

Selama Request masih terbuka, sebagian layar menyebutkan langkah yang sedang berjalan, misalnya `Waiting` diikuti nama langkah, sehingga Anda tahu giliran siapa. Kolom `Approval` pada `Overtime` juga menampilkan setiap langkah beserta hasilnya.

## Siapa yang menyetujui

Setiap modul memiliki rantai persetujuan yang diatur oleh HR atau administrator. Sebuah langkah dapat berupa:

- sebuah jabatan (siapa pun yang memegang jabatan itu dapat bertindak),
- satu orang atau lebih yang ditunjuk namanya,
- atasan langsung Anda, atau pemimpin Project Anda (dipakai pada Overtime),
- orang yang Anda pilih sendiri dari daftar saat mengajukan. Formulir lalu menampilkan pilihan wajib seperti `— choose an approver —`.

Langkah yang bersifat tetap tampil di formulir sebagai teks baca-saja agar Anda tahu siapa yang akan menandatangani.

## Status per modul

| Modul | Status yang dapat Anda lihat |
|---|---|
| Overtime | `Submitted`, `In review`, `Approved`, `Rejected`, `Cancelled` |
| Reimbursement | `Submitted`, `In review`, `Approved`, `Rejected` |
| Purchase Request | `Submitted`, `In review`, `Approved`, `Rejected`, `Cancelled` |
| Cash Advance dan Cash Advance Report | `Submitted`, `In review`, `Approved`, `Rejected`, `Cancelled` |
| Leave & Permit | `Pending`, `Approved`, `Ask for Edit`, `Rejected` |
| Koreksi Attendance | `Pending`, `Approved`, `Rejected`, `Cancelled` |
| Permintaan surat | `Pending`, `In Progress`, `Done`, `Rejected`, `Cancelled` |
| Timesheet | `Draft`, `Submitted`, `Approved`, `Rejected` |
| KPI | `Draft`, `Self-Assessed`, `Reviewed`, `Completed`, `Approved`, `Needs Revision` |

Sebuah Cash Advance juga menampilkan label kedua untuk laporannya: `Not reported`, `Reporting`, atau `Settled`. Cash Advance Report menampilkan cara penyelesaiannya: `Refund`, `Claim`, atau `Exact`.

## Yang dilakukan selanjutnya

### Setelah Request disetujui

Tidak ada tindakan tambahan untuk persetujuannya. Ikuti topik modul terkait untuk langkah berikutnya, seperti pembayaran atau pembuatan Cash Advance Report.

### Setelah Request ditolak

1. Buka Request dan baca alasannya pada jejak persetujuan atau catatan.
2. Perbaiki penyebabnya, misalnya bukti yang kurang atau tanggal yang salah.
3. Kirim Request baru. Dokumen Overtime, Reimbursement, Purchase Request, dan Cash Advance yang ditolak tidak diedit lalu dikirim ulang; dokumen baru dibuat.

Pada Leave & Permit, `Ask for Edit` berarti pemeriksa meminta perubahan: pilih `Edit` pada pengajuan, perbarui, lalu kirim lagi. Pada KPI, status `Needs Revision` menampilkan tombol `Revise`.

### Membatalkan

Anda hanya dapat membatalkan selama Request belum diproses, dan hanya jika modulnya mengizinkan. Reimbursement tidak dapat Anda batalkan sendiri; hubungi HR.

## Sebagai Approver

Item yang menunggu keputusan Anda dikumpulkan di `My Approvals`. Lihat [[my-approvals]]. Saat menolak, selalu tulis alasan yang jelas karena pemohon akan membacanya.

## Pertanyaan umum

**Request saya sudah berhari-hari `Submitted`.** Approver pertama belum bertindak. Periksa siapa Approver-nya pada Request tersebut dan ingatkan orangnya.

**Bisakah saya mengubah Request setelah dikirim?** Pada umumnya tidak; hanya HR yang dapat mengubah dokumen yang sudah dikirim. Batalkan bila modulnya mengizinkan, atau minta bantuan HR.

## Topik terkait

- [[my-approvals]]
- [[my-overtime]]
- [[my-reimbursement]]
- [[my-leave-permit]]
- [[navigating-ecosystem]]

<!-- sources: app/Services/Approval/ApprovalEngine.php, app/Services/Approval/ApproverResolver.php, app/Models/Overtime/OvertimeRequest.php, app/Models/Reimbursement/ReimbursementRequest.php, app/Models/PurchaseRequest/PurchaseRequest.php, app/Models/CashAdvance/CashAdvance.php, app/Models/CashAdvance/CashAdvanceReport.php, app/Models/KpiEvaluation.php, app/Models/Letters/LetterRequest.php, resources/views/hr-general/leave-permit/my-leave-permit.blade.php, resources/views/hr-general/attendance/my-attendance.blade.php, resources/views/calendar/timesheets.blade.php, app/Services/Reimbursement/ReimbursementService.php -->

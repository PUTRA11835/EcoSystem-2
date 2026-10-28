---
key: workflow-manager
chapter: reference
order: 20
title: Alur kerja atasan dan approver
summary: Yang dilakukan atasan atau approver terhadap permintaan yang menunggu keputusan.
requires: [general.approvals, "@approver"]
updated: 2026-10-27
---
Jika Anda ditetapkan sebagai approver, permintaan yang membutuhkan keputusan Anda dikumpulkan di `My Approvals`. Halaman ini menunjukkan rutinitasnya dan menautkan ke topik rinci.

## Siapa yang menggunakan

Atasan langsung, supervisor, dan siapa pun yang ditetapkan sebagai approver pada sebuah approval workflow. Anda tidak memerlukan menu HR untuk menyetujui permintaan yang ditujukan kepada Anda.

## Yang menunggu Anda

`My Approvals` berjudul "Everything that waits for your decision, across all modules". Isinya mencakup modul berikut:

- Overtime
- Reimbursement
- Purchase Request
- Cash Advance
- Cash Advance Report

Permintaan cuti memiliki kotak persetujuan sendiri di dalam `Leave & Permit`, bagi yang memiliki aksesnya. Lihat [[leave-permit-management]].

## Langkah demi langkah

1. Buka `My Approvals` dari sidebar di bawah `My Workspace`.
2. Baca daftarnya. Setiap baris menampilkan `Module`, `Document No.`, `Requester`, `Your step`, dan `Waiting since`.
3. Klik `Review` pada sebuah baris untuk membuka permintaan.
4. Baca rincian dan lampirannya, lalu pilih `Approve` atau `Reject`. Berikan alasan yang jelas saat menolak, karena pemohon akan melihatnya.
5. Ulangi sampai halaman menyatakan tidak ada yang menunggu Anda.

> [!TIP]
> Buka `My Approvals` sekali sehari. Permintaan tetap menunggu sampai Anda memprosesnya.

## Apa yang terjadi selanjutnya

- Jika workflow memiliki langkah berikutnya, permintaan berpindah ke approver berikutnya. Setelah langkah terakhir, statusnya menjadi `Approved`.
- Permintaan yang ditolak menjadi `Rejected` dan kembali kepada pemohon.
- Finance dan HR kemudian memproses permintaan yang disetujui, misalnya Reimbursement dan Overtime.
- Anda juga dapat mengajukan permintaan sendiri seperti pegawai lain. Lihat [[workflow-employee]].

## Pertanyaan umum

**Saya tidak melihat `My Approvals`.** Menu ini tampil hanya bila Anda ditetapkan pada sebuah approval workflow atau role Anda diberi akses. Hubungi administrator. Lihat [[approval-workflows]].

## Topik terkait

- [[my-approvals]]
- [[approval-workflows]]
- [[statuses-and-approvals]]
- [[workflow-employee]]

<!-- sources: resources/views/partials/sidebar.blade.php, resources/views/hr-general/approvals/index.blade.php, app/Services/Approval/ApprovalEngine.php -->

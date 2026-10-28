---
key: approval-workflows
chapter: administration
order: 30
title: Approval Workflow
summary: Tentukan siapa yang menyetujui Overtime, Reimbursement, Purchase Request, Cash Advance, dan Cash Advance Report, langkah demi langkah.
requires: [general.approval-workflow.overtime, general.approval-workflow.reimbursement, general.approval-workflow.purchase-request, management.approval-workflow.cash-advance, management.approval-workflow.cash-advance-report]
updated: 2026-10-27
---
Approval Workflow adalah daftar langkah berurutan yang dilalui sebuah Request sebelum berstatus `Approved`. Lima jenis Request memiliki workflow sendiri: Overtime, Reimbursement, Purchase Request, Cash Advance (CA), dan Cash Advance Report (CAR). Topik ini ditujukan untuk administrator.

## Siapa yang menggunakan

Administrator dan pemilik proses (HR, Finance, GA). Anda hanya melihat workflow yang aksesnya dimiliki Role Anda.

## Sebelum Anda mulai

- Role dan posisi harus sudah ada. Lihat [[roles-and-menu-access]].
- Setiap Approver juga memerlukan akses ke halaman persetujuan jenis Request tersebut, atau ia tidak dapat bertindak.

## Di mana setiap workflow berada

Editornya sama di mana pun. Anda membukanya dari tab `Settings` pada modul masing-masing:

| Jenis Request | Buka |
|---|---|
| Overtime | `Overtime Management` › `Settings` |
| Reimbursement | `Reimbursement Management` › `Settings` |
| Purchase Request | `Purchase Management` › `Settings` |
| Cash Advance dan Cash Advance Report | `Cash Advance Management` › `Settings` |

## Langkah demi langkah

### Mengubah langkah

1. Buka bagian `Approval Workflow` pada `Settings` modul.
2. Baca langkah dari atas ke bawah. Langkah berjalan sesuai urutan itu, dan satu orang per langkah sudah cukup.
3. Untuk setiap langkah, isi kolom yang berlaku: `Step name`, peran penandatangan (`Signs as`, pada Reimbursement), sumber `Approver` (Overtime), `Position`, dan `Name` orang tertentu.
4. Kosongkan `Name` agar semua orang yang memegang posisi itu dapat bertindak pada langkah tersebut. Pilih nama untuk membatasi langkah pada orang-orang itu.
5. Gunakan panah untuk memindahkan langkah, `Active` untuk menyalakan atau mematikannya, dan `Del` untuk menghapusnya. Klik `Add Step` untuk menambah langkah.
6. Klik `Save Workflow`. Perubahan berlaku bersamaan; baris yang belum disimpan ditandai `Unsaved`.

Pada Overtime, `Approver` dapat berupa `Fixed position / names`, `Employee's supervisor` (diambil dari data atasan langsung Employee), atau `Project leader`. Untuk dua yang terakhir, posisi menjadi cadangan.

### Langkah Mandatory

`Mandatory` dicentang berarti orang pada baris itu selalu bertindak dan pemohon hanya melihatnya. Bila tidak dicentang, pemohon memilih dari orang yang tercantum saat mengajukan.

### Menerapkan langkah baru pada dokumen yang sedang berjalan

Saat Anda menambah langkah, muncul kotak centang `Also apply new steps to … in progress`. Centang hanya bila dokumen yang sedang menunggu juga harus melewati langkah baru.

> [!WARNING]
> Dokumen yang sedang berjalan tetap memakai langkah awalnya kecuali Anda mencentang pilihan itu. Dokumen yang sudah `Approved` atau `Rejected` tidak pernah disentuh. Workflow harus memiliki minimal satu langkah, dan setiap langkah memerlukan nama atau role serta posisi atau nama; bila tidak, workflow tidak dapat disimpan.

## Apa yang terjadi selanjutnya

Request baru mengikuti langkah yang tersimpan. Approver melihat Request yang menunggu mereka di `My Approvals`, dan halaman Request menampilkan langkah saat ini serta siapa yang sudah bertindak.

## Pertanyaan umum

**Sebuah Request tertahan di satu langkah.** Periksa siapa yang memegang posisi atau nama pada langkah itu, dan apakah orangnya memiliki akses ke halaman persetujuan.

**Bisakah pemohon menyetujui Request-nya sendiri?** Hanya bila aturan modul mengizinkan persetujuan sendiri, dan hal itu selalu ditandai.

## Topik terkait

- [[my-approvals]]
- [[reimbursement-management]]
- [[purchase-management]]
- [[cash-advance-management]]
- [[statuses-and-approvals]]

<!-- sources: resources/views/hr-general/partials/approval-workflow/editor.blade.php, resources/views/hr-general/partials/approval-workflow/row.blade.php, resources/views/hr-general/*/components/step-editor.blade.php, routes/hr-general.php, app/Models/Reimbursement/ReimbursementApprovalStep.php -->

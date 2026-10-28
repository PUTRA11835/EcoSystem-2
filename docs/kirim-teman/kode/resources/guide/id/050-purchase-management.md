---
key: purchase-management
chapter: finance
order: 50
title: Purchase Management
summary: Proses Purchase Request dan Purchase Order, serta atur aturan, satuan, cost center, dan langkah persetujuan.
requires: [general.purchase-request, general.purchase-order, general.settings.purchase-request, general.approval-workflow.purchase-request]
updated: 2026-10-27
---
Purchase Management adalah tempat Approver dan Finance menangani Purchase Request yang diajukan Employee beserta Purchase Order yang menyusulnya. Halaman ini memiliki tiga tab: `Purchase Requests`, `Purchase Orders`, dan `Settings`.

## Siapa yang menggunakan

Approver, Finance, GA, dan administrator. Anda hanya melihat tab yang diizinkan untuk Role Anda, dan tombol seperti `New PR` atau `New Purchase Order` hanya tampil bila Anda memiliki akses yang sesuai.

## Sebelum Anda mulai

- Employee mengajukan sendiri di `My Workspace` › `Purchase Request` (lihat [[my-purchase-request]]).
- Langkah persetujuan disimpan di `Settings` › `Approval Workflow` (lihat [[approval-workflows]]).

## Langkah demi langkah

### Memproses Purchase Request

1. Buka `Purchase Management` › `Purchase Requests`.
2. Gunakan `Scope`, `Month`, `Status`, dan `Search`, lalu `Apply`, untuk mencari dokumen. Baris yang perlu perhatian diberi sorotan.
3. Buka dokumen dan baca item, jumlah, dan cost center.
4. Bila giliran Anda, setujui dokumen, atau tolak dengan alasan tertulis.
5. Status berjalan dari `Submitted`, `In review`, menjadi `Approved` atau `Rejected`. Permintaan menjadi `Cancelled` bila pemohon membatalkannya dan aturan mengizinkan.

Tindakan lain di daftar: `Edit`, `Print`, `Export Excel`, `Monthly Export`, dan `Delete`. `New PR` membuat permintaan atas nama Employee.

### Membuat Purchase Order

1. Buka `Purchase Orders` lalu klik `New Purchase Order`.
2. Pilih Purchase Request yang sudah disetujui pada kolom nomor PR, atau kosongkan untuk membuat order secara manual (tergantung aturan di `Settings`).
3. Isi `Supplier`, tanggal pembelian, `Invoice No.` dan `Invoice Date`, `Delivered Date`, serta `Notes`.
4. Tambahkan `Items` beserta harga satuan, diskon, dan pajak.
5. Simpan order. Statusnya dimulai dari `Submitted`.
6. Approver membuka order dan memakai `Approval Actions` untuk menyetujui atau menolaknya. `Print Form` mencetak order.

### Mengatur aturan (Settings)

1. Buka tab `Settings`.
2. Di `Approval Workflow`, tambah atau atur langkah, lalu klik `Save Workflow`.
3. Di `Purchase Request Rules`, periksa tanggal dan batas mundur, item dan kuantitas, satuan yang diizinkan, cost center, siapa yang boleh membatalkan, dan tanda tangan pada formulir cetak.
4. Simpan perubahan.

> [!NOTE]
> Perubahan aturan berlaku untuk dokumen baru. Dokumen yang sedang berjalan tetap memakai langkah awalnya, kecuali Anda mencentang pilihan untuk menerapkan langkah baru pada dokumen tersebut.

## Apa yang terjadi selanjutnya

Purchase Request yang disetujui dapat menjadi dasar Purchase Order. Dokumen `Approved` atau `Rejected` tidak bisa diubah seperti permintaan biasa; penghapusan memerlukan akses khusus dan tetap meninggalkan catatan.

## Pertanyaan umum

**Mengapa saya tidak melihat `Purchase Orders`?** Tab itu memerlukan akses tersendiri. Hubungi administrator.

**Bisakah pemohon membatalkan?** Hanya bila `Requesters may cancel their own request` aktif di `Settings`.

## Topik terkait

- [[my-purchase-request]]
- [[approval-workflows]]
- [[my-approvals]]
- [[workflow-finance]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/PurchaseRequestController.php, app/Http/Controllers/HR_General/PurchaseOrderController.php, resources/views/hr-general/purchase-request/*.blade.php, resources/views/hr-general/purchase-order/*.blade.php, app/Models/PurchaseRequest/PurchaseRequest.php -->

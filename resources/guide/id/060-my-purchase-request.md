---
key: my-purchase-request
chapter: my-workspace
order: 60
title: My Purchase Request
summary: Ajukan permintaan pembelian barang atau jasa yang belum dibeli, pantau persetujuannya, dan batalkan selama masih diizinkan.
requires: [general.my-purchase-request]
updated: 2026-10-27
---
Gunakan `My Purchase Request` untuk meminta perusahaan membelikan barang atau jasa bagi Anda atau tim Anda. Fitur ini untuk barang yang belum dibeli. Untuk biaya yang sudah Anda bayar, gunakan [[my-reimbursement]].

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `My Purchase Request`.

## Sebelum Anda mulai

Ketahui kebutuhan Anda, jumlah dan satuannya, kapan akan dipakai, serta cabang atau Project yang menanggung biayanya.

## Langkah demi langkah

### Mengajukan Purchase Request

1. Buka `My Purchase Request` dari sidebar lalu pilih `Submit Purchase Request`.
2. Jika ada pesan merah bahwa belum ada langkah persetujuan, berhenti. HR harus mengaturnya terlebih dahulu.
3. Periksa `Document No.`. Isinya `Generated on submit`.
4. Isi `Request Date`.
5. Tulis `Request Summary` dalam satu baris. Jelaskan untuk apa permintaan ini, bukan hanya item pertama.
6. Isi tabel item. Untuk setiap item isi `Description`, `Period`, `Qty`, `Unit`, `Use Date`, dan `Charged To` (cabang atau Project).
7. Pilih `Add Item Row` untuk menambah item, atau ikon hapus untuk menghapusnya.
8. Tambahkan `Notes` bila Approver perlu mengetahui sesuatu. Ini opsional.
9. Pada `Requester & Approver`, pilih Approver untuk setiap langkah yang meminta Anda memilih, dari daftar `— choose an approver —`.
10. Baca `Current rules`. Aturan ini menunjukkan apakah tanggal mendatang atau lampau diizinkan, batas item, kuantitas maksimum per item, apakah `Use Date`, `Period`, dan cost center wajib, serta apakah Anda boleh membatalkan.
11. Pilih `Submit Request` lalu konfirmasi.

### Memantau Request

1. Pada tabel `Purchase Requests`, baca `Document No.`, `Date`, `Summary`, `Item`, `Qty`, dan `Status`.
2. Pilih nomor dokumen untuk memperluas itemnya.
3. Gunakan ikon detail untuk jejak persetujuan dan ikon cetak untuk mencetak.

### Membatalkan Request

1. Temukan Request-nya. Tombol `Cancel` hanya muncul bila perusahaan mengizinkan pemohon membatalkan dan Request masih `Submitted`.
2. Pilih `Cancel` lalu konfirmasi. Anda dapat membatalkan sampai Approver pertama bertindak.

> [!WARNING]
> Request yang dibatalkan tidak dapat dipulihkan. Anda harus mengirim Request baru.

## Apa yang terjadi selanjutnya

Request berpindah dari `Submitted` ke `In review` saat Approver bertindak, lalu menjadi `Approved` atau `Rejected`. Anda diberi tahu hasilnya. Jika halaman menyatakan `Once submitted, only HR can change a request`, minta HR untuk perubahan. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Mengapa `Use Date` atau `Period` ditandai?** Bila kolom ini opsional tetapi kosong, Request ditandai untuk Approver, bukan ditolak.

**Bolehkah saya mencampur beberapa cabang atau Project?** Boleh. Pilih `Charged To` per item; dokumen lalu menampilkan cost center campuran.

## Topik terkait

- [[my-reimbursement]]
- [[my-cash-advance]]
- [[statuses-and-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyPurchaseRequestController.php, resources/views/hr-general/purchase-request/my-purchase-request.blade.php, resources/views/hr-general/purchase-request/submit.blade.php, resources/views/hr-general/purchase-request/components/item-rows.blade.php, app/Models/PurchaseRequest/PurchaseRequest.php -->

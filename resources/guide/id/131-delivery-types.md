---
key: delivery-types
chapter: administration
order: 131
title: Project Type, Support Type dan Document Type
summary: Pelihara daftar yang dipakai di formulir Delivery Project, formulir Delivery Support, dan formulir dokumen tiket.
requires: [management.delivery.project, management.delivery.support, management.ticket.document-type]
updated: 2026-10-28
---
Tiga halaman pengaturan ini menyimpan pilihan yang dilihat orang di formulir: tipe proyek pada delivery project, tipe support pada item delivery support, dan tipe dokumen deliverable pada tiket.

## Siapa yang menggunakan

Administrator, dan role yang diberi `Management` › `Project Type`, `Support Type` atau `Document Type` di Menu Access. Anda hanya melihat halaman yang dimiliki role Anda.

## Sebelum Anda mulai

- Daftar ini mengisi formulir yang dipakai banyak orang. Mengganti nama atau menonaktifkan sebuah nilai mengubah apa yang dilihat semua orang berikutnya.
- Pilih `Inactive` daripada `Delete` bila sebuah nilai hanya dipensiunkan, agar pilihan berhenti muncul tanpa menghapus catatannya.

## Langkah demi langkah

### Project Type dan Support Type

1. Buka `Management` › `Project Type` (untuk formulir Delivery Project) atau `Management` › `Support Type` (untuk formulir Delivery Support).
2. Pakai filter status untuk menampilkan nilai `Active` atau `Inactive`.
3. Pilih `Add Project Type` atau `Add Support Type`, isi nama dan deskripsi (opsional), biarkan `Active` tercentang, lalu pilih `Save`.
4. Pakai `Edit` untuk mengganti nama atau mengalihkan nilai antara aktif dan tidak aktif, dan `Delete` untuk menghapus.

### Document Type

![Jendela Add Document Type](images/guide/document-type-add.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Management` › `Document Type`. Daftar menampilkan tiap tipe beserta deskripsi, tipe tiket yang membutuhkannya dan apakah `Mandatory` atau `Optional`, serta statusnya.
2. Pilih `Add Document Type`. Isi `Document Type Name` (misalnya IR, RCA, atau CR Form) dan deskripsi.
3. Di `Ticket Type`, pilih untuk setiap tipe tiket salah satu dari `Not required`, `Optional` atau `Mandatory`. Ini menjadi default untuk tiket bertipe itu.
4. Atur status menjadi `Active` agar tipe muncul di daftar dokumen tiket.
5. Pilih `Save`. Pakai `Edit` atau `Delete` pada sebuah baris kemudian.

## Apa yang terjadi selanjutnya

Nilai baru atau yang diubah muncul di formulir terkait saat seseorang membukanya berikutnya. Tipe dokumen yang aktif muncul di formulir `New Document` dan di daftar jendela `Deliverable Documents` pada tiket. Setiap tiket dimulai dengan pilihan `Mandatory` dan `Optional` default dari tipe tiketnya, dan perubahan pada default berlaku ke semua tiket bertipe itu yang belum diubah manual.

Pada tiket, buka `Deliverable Documents` lalu pilih `Edit Requirement` untuk mengubah dokumen mana yang mandatory khusus untuk tiket itu, kemudian `Save`. Member tiket tidak dapat melakukannya. Tiket yang sudah diubah menampilkan `Customized for this ticket`; pilih `Reset to Default` untuk kembali mengikuti tipe tiketnya. Mengganti tipe tiket juga mengembalikan tiket ke default tipe yang baru.

## Pertanyaan umum

**Tipe baru tidak ada di formulir.** Pastikan statusnya `Active`, lalu muat ulang formulir.

**Saya tidak sengaja menghapus sebuah tipe.** Tambahkan lagi dengan nama yang sama. Penghapusan tidak dibatalkan otomatis.


<!-- sources: routes/web.php, app/Http/Controllers/DeliveryProjectTypeController.php, app/Http/Controllers/DeliverySupportTypeController.php, app/Http/Controllers/DeliverableDocumentTypeController.php -->

---
key: my-cash-advance
chapter: my-workspace
order: 70
title: My Cash Advance
summary: Ajukan Cash Advance lalu pertanggungjawabkan dengan Cash Advance Report sebelum batas waktu.
requires: [general.my-cash-advance, general.my-cash-advance-report]
updated: 2026-10-27
---
Cash Advance (CA) adalah uang yang diberikan perusahaan kepada Anda sebelum Anda membelanjakannya. Setelah disetujui dan dipakai, Anda mempertanggungjawabkannya dengan Cash Advance Report (CAR). Keduanya ada di satu halaman dengan dua tab: `My Cash Advance` dan `My Cash Advance Report`.

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `My Cash Advance`.

## Sebelum Anda mulai

Ketahui nominal, tujuan, serta cabang atau Project yang menanggung biayanya. Untuk laporan, simpan struk dan catat tanggal serta nominal setiap pengeluaran.

## Langkah demi langkah

### Mengajukan Cash Advance

1. Buka `My Cash Advance` dari sidebar.
2. Pilih `+ Submit CA`.
3. Jika ada pesan bahwa pengaturan belum siap, tunggu sampai HR mengonfigurasi `Cash Advance Settings`.
4. Periksa `Document No.` (dibuat otomatis saat kirim) dan isi `Date`. Gunakan `Date (until)` bila Cash Advance mencakup suatu periode.
5. Tulis `Description`, satu baris yang dicetak pada dokumen.
6. Pilih `Currency` dan isi `Amount`.
7. Tambahkan `Detail URL` bila Anda memiliki tautan berisi rincian. Kolom ditandai bila wajib.
8. Pilih `Charged To` sebagai cabang atau Project, lalu pilih targetnya di `Charged To — target`.
9. Pilih `Approver` bila formulir meminta Anda memilih. Tambahkan catatan bila perlu.
10. Kirim formulir.

### Memantau Cash Advance

1. Baca kartu `Waiting review`, `Outstanding` (disetujui, belum dilaporkan), dan `Reports overdue`.
2. Pada tabel baca `Document No.`, `CAR`, `Date`, `Description`, `Approver`, `Amount`, dan `Status`.
3. Pilih nomor dokumen untuk melihat rincian. Cetak dokumen dari sana bila perlu.
4. Pilih `Cancel` bila tombolnya tampil. Tombol ini hanya muncul selama perusahaan mengizinkan pembatalan dan belum ada yang bertindak.

### Melaporkan pengeluaran

1. Saat CA disetujui, kolom `CAR` menampilkan `Create CAR`. Sebuah badge juga menunjukkan batas waktunya, seperti `Report due in 3 days` atau `Report overdue`.
2. Pilih `Create CAR`. Halaman menampilkan nomor dokumen dan nominal advance yang Anda selesaikan.
3. Tulis deskripsi laporan.
4. Tambahkan `Expense lines`. Untuk setiap baris isi `Date`, `Description`, `Receipt No.` (opsional), `Amount`, `Charged To`, dan `Receipt link`. Pilih tombol tambah untuk menambah baris.
5. Periksa `Reported total`.
6. Pilih Approver bila diminta, tambahkan `Additional Notes`, lalu pilih `Submit`.

Pada tab `My Cash Advance Report`, panel `Waiting to be reported` mencantumkan advance yang sudah disetujui dan masih perlu laporan. Tabel laporan menampilkan `Advance`, `Reported`, dan `Settlement`.

## Apa yang terjadi selanjutnya

CA Anda mengikuti langkah persetujuan: `Submitted`, `In review`, lalu `Approved`. Setelah disetujui, label laporannya bergerak melalui `Not reported`, `Reporting`, dan `Settled`. Penyelesaian sebuah laporan berupa salah satu dari:

- `Refund`: pengeluaran Anda lebih kecil dari advance, sehingga Anda mengembalikan selisihnya.
- `Claim`: pengeluaran Anda lebih besar, sehingga perusahaan membayar selisihnya kepada Anda.
- `Exact`: nominalnya sama.

Jika Anda membatalkan laporan, Cash Advance-nya kembali menunggu laporan. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Tidak ada tombol `Create CAR`.** CA belum disetujui, atau laporannya sudah ada.

**Saya melewatkan batas waktu.** Badge menjadi merah dan `Reports overdue` menghitungnya. Ajukan laporan secepatnya.

## Topik terkait

- [[my-reimbursement]]
- [[my-purchase-request]]
- [[statuses-and-approvals]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/cash-advance/my-cash-advance.blade.php, resources/views/hr-general/cash-advance/submit.blade.php, resources/views/hr-general/cash-advance/report-index.blade.php, resources/views/hr-general/cash-advance/report-submit.blade.php, resources/views/hr-general/cash-advance/components/car-due-badge.blade.php, app/Models/CashAdvance/CashAdvance.php, app/Models/CashAdvance/CashAdvanceReport.php -->

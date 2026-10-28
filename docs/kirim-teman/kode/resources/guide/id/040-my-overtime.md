---
key: my-overtime
chapter: my-workspace
order: 40
title: Overtime
summary: Ajukan Overtime, lihat siapa yang harus menyetujuinya, dan batalkan selama masih menunggu.
requires: [general.my-overtime]
updated: 2026-10-27
---
Halaman `Overtime` adalah tempat Anda mengajukan dan memantau Overtime sendiri. Halaman ini mencantumkan dasar hukumnya (Pasal 78 Undang-Undang Ketenagakerjaan dan Peraturan Pemerintah No. 35 Tahun 2021) agar Anda tahu atas dasar apa Overtime dihitung.

## Siapa yang menggunakan

Setiap Employee yang memiliki menu `Overtime` di bawah `My Workspace`. Approver memeriksa Request di `My Approvals` atau di Overtime Management.

## Sebelum Anda mulai

Ketahui tanggal, jam mulai dan selesai, serta pekerjaan yang Anda lakukan. Halaman dapat membandingkan klaim Anda dengan catatan Attendance, jadi periksa `My Attendance` terlebih dahulu.

## Langkah demi langkah

### Mengajukan Overtime

1. Buka `Overtime` dari sidebar. Kartu menampilkan `Submitted`, `Approved`, `Waiting Review`, dan `Approved Hours` Anda untuk bulan ini.
2. Pilih `Submit Overtime`.
3. Baca kotak `Your request will be reviewed by`. Kotak ini mencantumkan langkah persetujuan. Jika ada pesan merah bahwa belum ada langkah persetujuan, Anda tidak dapat mengirim sampai HR mengaturnya.
4. Isi `Overtime Date`.
5. Isi `Start Time` dan `End Time`. Formulir menampilkan `Calculated duration`. Jika jam selesai jatuh pada hari berikutnya, formulir menyatakannya.
6. Periksa perbandingan Attendance yang muncul di bawah jam. Jika klaim Anda tidak sesuai dengan Attendance, Anda tetap dapat mengirim, tetapi Request ditandai untuk pemeriksa.
7. Tulis `Reason`. Panjangnya harus memenuhi batas minimum yang tertera pada placeholder.
8. Baca `Current rules` di sisi kanan. Aturan ini menunjukkan apakah tanggal mendatang dan melewati tengah malam diizinkan, seberapa jauh ke belakang Anda boleh mengklaim, serta batas harian. Melebihi batas ditandai untuk ditinjau, bukan diblokir.
9. Pilih `Submit Request` lalu konfirmasi.

Beberapa Request pada hari yang sama diizinkan selama jamnya tidak saling tumpang tindih.

### Memantau Request

1. Pada tabel `Overtime Requests`, baca `Overtime No.`, `Date`, `Time`, `Duration`, dan `Status`.
2. Baca kolom `Approval`. Setiap langkah menampilkan titik berwarna (disetujui, ditolak, dilewati, atau menunggu) beserta catatan Approver.
3. Jika Approver mengubah jam Anda, baris menampilkan `Adjusted from` diikuti jam asli.

### Membatalkan Request

1. Temukan Request di tabel.
2. Pilih `Cancel` dan konfirmasi. Tombol ini hanya tampil selama Request masih menunggu pemeriksa pertama.

## Apa yang terjadi selanjutnya

Saat Request melewati langkah-langkahnya, `Status` berubah dari `Submitted` ke `In review` (atau `Approved by` diikuti nama langkah) dan akhirnya `Approved` atau `Rejected`. Anda menerima Notification berisi hasilnya. Overtime yang ditolak tidak dapat diedit; kirim Request baru. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Tombol `Cancel` hilang.** Seorang Approver sudah memprosesnya. Hubungi HR.

**Mengapa Request saya ditandai?** Alasan umumnya adalah ketidakcocokan dengan Attendance, tanggal mendatang, durasi di atas batas harian, atau catatan Attendance yang tidak ada. Tanda tidak berarti Request ditolak; hanya meminta pemeriksa lebih teliti.

## Topik terkait

- [[my-attendance]]
- [[my-approvals]]
- [[statuses-and-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyOvertimeController.php, resources/views/hr-general/overtime/my-overtime.blade.php, resources/views/hr-general/overtime/submit.blade.php, app/Models/Overtime/OvertimeRequest.php -->

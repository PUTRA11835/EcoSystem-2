---
key: report-diagram
chapter: reporting
order: 108
title: Diagram Report
summary: Sepuluh grafik data tiket yang mengikuti satu filter customer dan periode.
requires: [reporting.diagram-report]
updated: 2026-10-28
---
Diagram Report menampilkan analitik tiket sebagai sepuluh grafik yang semuanya mengikuti filter customer dan periode yang sama.

## Siapa yang menggunakan

Siapa pun yang punya menu `Reporting` › `Diagram Report`, misalnya module lead, direktur, dan administrator.

## Sebelum Anda mulai

- Grafik tetap kosong sampai Anda mengisi tanggal mulai dan tanggal akhir di `Period`.
- Datanya langsung: membaca tiket saat ini.

## Langkah demi langkah

![Diagram Report dengan periode terpilih, menampilkan grafik pertama](images/guide/report-diagram.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Diagram Report`.
2. Pilih `Customer`, atau biarkan `All Customers`.
3. Buka `Period`, isi `Start Date` dan `End Date`, lalu pilih `Apply`. Rentang yang dipilih tampil di tombol dan di bawah filter. `Clear Filter` menghapusnya.
4. Gulir sepuluh grafiknya:
   - Chart 1: jumlah tiket per bulan, dari awal sampai periode sekarang.
   - Chart 2: tiket per modul, menurut jenis tiket.
   - Chart 3: tipe tiket per bulan, dalam bentuk tabel.
   - Chart 4: tipe tiket per modul.
   - Chart 5: tipe tiket menurut modul, dalam tabel dengan total.
   - Chart 6: jumlah tiket per modul.
   - Chart 7: jumlah tiket menurut tipe.
   - Chart 8: Change Request dibanding tiket lain, menurut status terbuka atau selesai.
   - Chart 9: Change Request dan tiket lain per bulan.
   - Chart 10: tiket selesai per bulan.

## Apa yang terjadi selanjutnya

Semua grafik diperbarui bersama saat Anda mengganti customer atau periode.

## Pertanyaan umum

**Grafik meminta memilih tanggal.** Isi `Start Date` dan `End Date` di `Period`, lalu pilih `Apply`.

**Sebuah grafik kosong setelah saya memilih periode.** Tidak ada tiket yang cocok dengan customer dan rentang itu. Lebarkan tanggal atau pilih `All Customers`.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/diagram-report.blade.php -->

---
key: reports-overview
chapter: reporting
order: 10
title: Ikhtisar Reporting
summary: Laporan apa saja yang tersedia, cara pengelompokannya, serta cara memakai filter dan Export.
requires: [reporting]
updated: 2026-10-27
---
Bagian `Reporting` mengumpulkan laporan baca-saja tentang Project delivery, Support, dan Ticket. Semuanya bekerja dengan cara serupa: pilih periode atau filter, baca angkanya, lalu Export bila Anda memerlukan berkas.

## Siapa yang menggunakan

Manajer, kepala delivery, finance, dan administrator. Anda hanya melihat laporan yang boleh dibuka oleh role Anda.

## Daftar laporan

| Kelompok di sidebar | Laporan | Isinya |
|---|---|---|
| `Project Reports` | `Collection Outlook` | Rencana penagihan per Project dan bulan |
| `Project Reports` | `Consultant Assignment` | Consultant yang ditugaskan pada tiap Project |
| `Support Reports` | `MD Validation` | Mandays Timesheet dibandingkan dengan kuota |
| `Support Reports` | `MD Recap` | Mandays yang disetujui per pegawai dan mode |
| `Support Reports` | `Collection Outlook (Support)` | Rencana penagihan kontrak Support |
| `Support Reports` | `Ticketing Overview` | Jumlah Ticket per customer menurut status |
| `Support Reports` | `Ticket by Module` | Ticket dikelompokkan menurut modul |
| `Support Reports` | `Log Shifting` | Pesan SLA per Ticket |
| `Support Reports` | `Resolution Days` | Ticket yang resolution days-nya belum disetujui |
| Berdiri sendiri | `Diagram Report` | Sepuluh grafik tentang Ticket |

Rinciannya ada di [[report-ticketing]] (laporan Ticket dan Timesheet) dan [[report-delivery]] (penagihan, penugasan, dan grafik).

## Langkah demi langkah

### Membuka dan menyaring laporan

1. Buka `Reporting` di sidebar lalu perluas `Project Reports` atau `Support Reports`.
2. Pilih laporan. Tunggu sampai `Loading` selesai.
3. Atur filter. Yang umum adalah rentang tanggal (`From` dan `To`), bulan dan tahun, customer, atau status. Bila ada rentang, `To` harus sama dengan atau setelah `From`.
4. Manfaatkan kartu ringkasan di bagian atas. Pada beberapa laporan, mengklik kartu menyaring tabel di bawahnya ke kelompok tersebut.
5. Gunakan tombol filter pada judul kolom untuk mencari atau mengurutkan kolom itu, dan `Clear` atau `Reset Filters` untuk menghapus filter.

### Mengekspor laporan

1. Periksa periode dan filter di layar sebelum Export.
2. Klik tombol Export laporan tersebut, misalnya `Export MD`, `Export MD Recap`, `Export Excel`, atau `Export`.
3. Buka berkas Excel yang terunduh.

> [!TIP]
> Jika tabel menyatakan tidak ada data, perlebar rentang tanggal atau hapus filter status sebelum menyimpulkan bahwa laporan kosong.

## Apa yang terjadi selanjutnya

Laporan selalu menampilkan data terkini. Buka ulang atau segarkan halaman untuk melihat perubahan yang dibuat rekan Anda.

## Pertanyaan umum

**Laporan yang saya perlukan tidak ada di sidebar.** Setiap laporan memiliki aksesnya sendiri. Hubungi administrator.

**Dapatkah saya mengubah data di laporan?** Umumnya tidak. Pengecualiannya adalah status pembayaran di dua laporan `Collection Outlook`, yang dapat diubah oleh orang dengan akses yang sesuai.

## Topik terkait

- [[report-ticketing]]
- [[report-delivery]]
- [[tickets]]
- [[sla]]

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/partials/sidebar.blade.php, resources/views/reporting/ -->

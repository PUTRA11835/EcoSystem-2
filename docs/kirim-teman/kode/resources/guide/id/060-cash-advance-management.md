---
key: cash-advance-management
chapter: finance
order: 60
title: Cash Advance Management
summary: Setujui Cash Advance (CA) dan laporan penyelesaiannya (CAR), pantau laporan terlambat, dan atur aturannya.
requires: [general.cash-advance, general.cash-advance-report, general.cash-advance-dashboard, management.cash-advance-settings, management.approval-workflow.cash-advance, management.approval-workflow.cash-advance-report]
updated: 2026-10-27
---
Cash Advance (CA) adalah uang yang diberikan kepada Employee sebelum dibelanjakan. Setelah itu Employee membuat Cash Advance Report (CAR) untuk menyelesaikannya. Cash Advance Management adalah tempat Approver dan Finance memproses kedua dokumen tersebut. Halaman ini memiliki empat tab: `Dashboard`, `Cash Advance (CA)`, `Cash Advance Report (CAR)`, dan `Settings`.

## Siapa yang menggunakan

Approver, Finance, dan administrator. Anda hanya melihat tab yang diizinkan untuk Role Anda, dan setiap tombol memerlukan akses yang sesuai.

## Sebelum Anda mulai

- Employee mengajukan advance sendiri di `My Workspace` › `Cash Advance` dan melaporkannya di `Cash Advance Report` (lihat [[my-cash-advance]]).
- Langkah persetujuan untuk CA dan CAR terpisah. Lihat [[approval-workflows]].

## Langkah demi langkah

### Membaca Dashboard

`Dashboard` menunjukkan posisi CA dan CAR serta apa yang perlu Anda tangani: `CA waiting for you`, `CAR waiting for you`, `Oldest outstanding`, tampilan `Last 6 Months`, dan uang yang harus diselesaikan: `To collect (refund)` bila Employee membelanjakan lebih sedikit dari yang diterima, dan `To pay (claim)` bila membelanjakan lebih banyak.

### Menyetujui atau menolak CA

1. Buka `Cash Advance (CA)`.
2. Buka dokumen dan periksa nominal, tujuan, cost center, dan timeline persetujuan.
3. Bila giliran Anda, klik `Approve`, atau klik `Reject…` dan isi alasan.
4. Gunakan `New CA` untuk membuat advance atas nama Employee, bila Anda memiliki akses.

CA berjalan melalui `Submitted`, `In review`, `Approved`, `Rejected`, atau `Cancelled`. Secara terpisah, CA yang disetujui memiliki status penyelesaian: `Not reported`, `Reporting`, atau `Settled`.

### Memproses CAR

1. Buka `Cash Advance Report (CAR)`.
2. Bila ada kotak berisi advance yang menunggu dilaporkan, gunakan `+ New CAR` di samping advance yang tepat untuk memulai laporannya.
3. Buka laporan, lalu klik `Approve`, atau `Reject…` dengan alasan.
4. Menyetujui langkah terakhir akan menyelesaikan advance terkait dan menutupnya.

### Memantau laporan terlambat

Setiap CA yang disetujui memiliki batas waktu pelaporan. Laporan yang dikirim setelah tanggal jatuh tempo ditandai `Late report`, dan advance tampil sebagai `Overdue` di daftar. Pengingat harian dikirim kepada pemohon sebelum dan sesudah batas waktu.

### Mengatur aturan (Settings)

1. Buka tab `Settings`.
2. Di `Cash Advance Rules`, periksa batas seperti batas mundur tanggal, nominal minimum dan maksimum serta tindakan bila melebihi maksimum, mata uang yang diizinkan, panjang minimum deskripsi, jenis cost center, dan periode akuntansi terkunci.
3. Di `Settlement Rules (CAR)`, atur `Reporting deadline (days)` (0 berarti tanpa batas waktu) dan pengingat pertama.
4. Klik `Save settings`.
5. Halaman yang sama juga memuat Approval Workflow untuk CA dan CAR. Masing-masing hanya tampil bila Anda memiliki aksesnya. Klik `Save Workflow` setelah mengubah langkah.

## Apa yang terjadi selanjutnya

Advance yang `Settled` ditutup. Nominal refund atau claim tampil di `Dashboard` sampai ditangani.

## Pertanyaan umum

**Bisakah saya mengubah langkah persetujuan untuk dokumen yang sedang berjalan?** Langkah disalin ke setiap dokumen saat dibuat, jadi perubahan berlaku untuk dokumen baru, kecuali Anda mencentang pilihan untuk menerapkan langkah baru pada dokumen yang sedang berjalan.

**Mengapa saya tidak melihat sebuah tab?** Setiap tab memiliki akses tersendiri. Hubungi administrator.

## Topik terkait

- [[my-cash-advance]]
- [[approval-workflows]]
- [[my-approvals]]
- [[workflow-finance]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/CashAdvance*.php, resources/views/hr-general/cash-advance/*.blade.php, app/Models/CashAdvance/CashAdvance.php -->

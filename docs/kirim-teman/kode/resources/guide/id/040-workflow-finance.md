---
key: workflow-finance
chapter: reference
order: 40
title: Alur kerja Finance
summary: Cara Finance menangani Payroll, Reimbursement, pembelian, dan Cash Advance dari permintaan hingga pembayaran.
requires: [finance.payroll, general.reimbursement, general.purchase-request]
updated: 2026-10-27
---
Finance menerima permintaan yang sudah disetujui dari pegawai dan menjalankan Payroll bulanan. Halaman ini menunjukkan bagaimana pekerjaan tersebut saling terkait dan menautkan ke topik rinci.

## Siapa yang menggunakan

Staf Finance dan siapa pun yang mengelola Payroll, Reimbursement, pembelian, atau Cash Advance. Anda hanya melihat menu yang dimiliki role Anda.

## Ritme bulanan

| Pekerjaan | Lokasi | Yang Anda lakukan | Rincian |
|---|---|---|---|
| Payroll | `Finance` › `Payroll` | Buat periode, hitung, buat slip, publikasikan | [[payroll]] |
| BPJS | `Finance` › `BPJS` | Tinjau laporan iuran dan surat | [[bpjs]] |
| PPh 21 | `Finance` › `PPh 21` | Tinjau pengaturan dan laporan pajak | [[pph21]] |
| Reimbursement | `Finance` › `Reimbursement Management` | Proses klaim yang sudah disetujui | [[reimbursement-management]] |
| Pembelian | `Finance` › `Purchase Management` | Proses Purchase Request dan Purchase Order yang disetujui | [[purchase-management]] |
| Cash Advance | `Finance` › `Cash Advance Management` | Proses Cash Advance beserta laporannya | [[cash-advance-management]] |

## Langkah demi langkah

1. Sebelum bulan berakhir, pastikan Attendance, cuti, dan lembur sudah beres. Payroll membacanya dari periode presensi yang Anda tetapkan saat membuat periode.
2. Di `Payroll`, klik `Create period` lalu isi awal dan akhir periode presensi.
3. Buka periodenya dan klik `Calculate` (atau `Recalculate` setelah ada perubahan). Tinjau angka per pegawai.
4. Klik `Generate Slip` untuk memberi setiap pegawai nomor slip. Pegawai belum dapat melihat slip.
5. Klik `Publish Payslip`. Pegawai kini melihat slipnya di `Paystub` dan dapat mengunduhnya sebagai PDF.
6. Jika opsi otomatis di `Settings` Payroll aktif, `Calculate` juga membuat dan mempublikasikan slip, jadi periksa angkanya terlebih dahulu.
7. Perhatikan peringatan tentang pegawai yang memiliki take-home pay tetapi belum punya rekening bank. Mereka tidak masuk ke berkas transfer bank sampai Anda melengkapi data banknya atau membayarnya secara terpisah.
8. Sepanjang bulan, kerjakan Reimbursement, Purchase Request, dan Cash Advance yang sudah disetujui di halaman pengelolaannya.

> [!IMPORTANT]
> Slip yang sudah dipublikasikan langsung terlihat oleh pegawai. Tinjau perhitungan sebelum Anda mempublikasikan.

## Apa yang terjadi selanjutnya

Pegawai membaca slip mereka di `Paystub`. Permintaan yang Anda selesaikan berpindah ke status akhirnya dan pemohon diberi tahu. Lihat [[workflow-employee]] untuk sisi pegawai.

## Pertanyaan umum

**Tombol Payroll tidak tampil.** Setiap tindakan memiliki aksesnya sendiri. Hubungi administrator.

**Dari mana permintaan berasal?** Pegawai mengajukannya dan approver memutuskan di `My Approvals`. Lihat [[workflow-manager]].

## Topik terkait

- [[payroll]]
- [[workflow-hr-admin]]
- [[workflow-manager]]
- [[faq]]

<!-- sources: resources/views/finance/payroll/index.blade.php, resources/views/finance/payroll/show.blade.php, resources/views/finance/payroll/settings.blade.php, resources/views/partials/sidebar.blade.php -->

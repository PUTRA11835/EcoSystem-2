---
key: payroll
chapter: finance
order: 10
title: Payroll
summary: Buat periode payroll, hitung gaji, buat dan publikasikan slip gaji, lalu kunci periode.
requires: [finance.payroll, finance.payroll.periods, finance.payroll.settings, finance.payroll.simulation]
updated: 2026-10-27
---
Payroll mengubah komponen gaji, kehadiran, cuti, lembur, dan reimbursement yang sudah disetujui menjadi slip gaji bulanan, lengkap dengan BPJS dan PPh 21. Anda bekerja satu periode demi satu periode: membuat, menghitung, memeriksa, menerbitkan slip, lalu mengunci.

## Siapa yang menggunakan

Staf Finance dan payroll. Halaman ini memiliki tiga tab: `Periods`, `Simulation`, dan `Settings`. Anda hanya melihat tab yang diizinkan untuk Role Anda. Bila ada tombol yang tidak tampil, minta administrator memberi izin akses.

## Sebelum Anda mulai

- Pastikan setiap Employee memiliki komponen gaji, status pajak (PTKP), dan data BPJS di `Master` › `Employee` (tab `Compensation` dan `Contract`). Payroll hanya memakai data yang sudah terisi.
- Pastikan tarifnya benar di [[bpjs]] dan [[pph21]].
- Setujui atau tolak dulu permintaan Overtime, Leave & Permit, koreksi Attendance, dan Reimbursement pada periode tersebut. Payroll hanya menghitung item yang sudah `Approved`.

> [!IMPORTANT]
> Payroll nonaktif sampai orang yang memiliki izin "Switch payroll on/off" mengaktifkannya di `Payroll` › `Settings` (`Payroll module`, `Switch payroll on`). Selama nonaktif, Anda tetap bisa melihat data, memakai `Settings` dan `Simulation`, tetapi tidak bisa membuat, menghitung, atau mengunci periode. Sebuah banner di halaman akan memberi tahu bila payroll nonaktif. Pastikan data gaji, PTKP, BPJS, dan PPh 21 sudah benar sebelum mengaktifkannya.

## Langkah demi langkah

### Membuat periode

1. Buka `Commercial & Finance` › `Payroll` › `Periods`.
2. Pada `Create period`, isi `Name`, `Start`, `End`, dan `Pay date`.
3. Periksa `Attendance period start` dan `Attendance period end`. Kehadiran, cuti, dan lembur dibaca dari rentang ini.
4. Bila perlu, isi `Cut-off date (optional)`. Hari kerja setelah tanggal ini dianggap hadir penuh dan dikoreksi pada payroll berikutnya.
5. Klik `Create period`. Periode muncul di daftar dengan status `Open`.

Satu periode maksimal 62 hari dan tidak boleh tumpang tindih dengan periode lain.

### Menghitung

1. Buka periode dengan tombol `Open` pada daftar.
2. Klik `Calculate`. Setelah perhitungan pertama, tombolnya bernama `Recalculate`.
3. Baca total di bagian atas dan peringatan dari hasil perhitungan.
4. Klik `Detail` pada baris Employee untuk melihat rinciannya, atau `Recalculate` untuk menghitung ulang satu Employee saja.
5. Untuk menyalakan atau mematikan BPJS Kesehatan, BPJS Ketenagakerjaan, atau PPh 21 bagi satu orang pada periode ini, ubah kotak centang di baris Employee lalu klik `Apply & Recalculate`.

### Menambah koreksi

Bila periode sebelumnya terlalu kecil atau terlalu besar dibayarkan, gunakan `Payroll corrections` › `Add correction`. Pilih Employee dan `Type` (`Underpaid`, `Overpaid`, atau `Adjustment`), isi nominal dan alasan, lalu klik `Save correction`. Klik `Recalculate` agar koreksi berlaku. Koreksi dapat dihapus dengan `Remove` selama periode masih `Open`.

### Membuat dan mempublikasikan slip

1. Klik `Generate Slip`. Setiap Employee mendapat nomor slip dan kode verifikasi. Slip yang sudah dibuat tidak berubah.
2. Klik `Publish Payslip`. Employee kini melihat slipnya di `My Workspace` › `Paystub` dan dapat mengunduh PDF.

Slip dapat dibuka dari baris Employee (`Slip ID` atau `Slip EN` untuk bahasa PDF).

### Mengunci periode

1. Periksa semua nominal.
2. Klik `Lock`, lalu konfirmasi.
3. Status berubah menjadi `Locked`. Setelah terkunci, file `Bank Transfer` dapat diunduh.

> [!WARNING]
> Periode yang sudah `Locked` tidak bisa dihitung ulang, dikoreksi, atau dihapus. Kunci hanya setelah semua nominal diperiksa.

## Export

`Export Excel` mengunduh data payroll periode itu. `Bank Transfer` mengunduh file transfer, hanya setelah `Lock`. Employee tanpa rekening bank tidak ikut; peringatan di halaman menyebutkan jumlahnya.

## Simulation

`Simulation` memungkinkan Anda mencoba skenario gaji dengan mengetik nilai, tanpa menyimpan payroll. Rumus BPJS, pajak, dan potongannya sama dengan payroll sungguhan. Klik `Run simulation` untuk melihat hasilnya.

## Settings

Di `Settings` Anda mengatur aturan untuk semua periode, misalnya jadwal kerja dan prorata, potongan alpa, denda terlambat, hari sakit/cuti/izin yang dibayar, dasar upah lembur, pembulatan, cut-off, dan penandatangan slip (HR dan Finance). Simpan dengan `Save payroll settings`. Dua saklar yang perlu diketahui:

- `Require approvals before calculating`: menolak `Calculate` selama masih ada permintaan pada periode itu yang menunggu persetujuan.
- `Publish payslips automatically after calculating`: setelah `Calculate`, slip langsung diberi nomor dan dipublikasikan. Biarkan mati bila Anda ingin memeriksa dulu.

Item bertanda "not connected yet" (tunjangan timesheet, potongan pinjaman) tersimpan tetapi belum memengaruhi slip.

## Apa yang terjadi selanjutnya

Slip yang dipublikasikan tampil di `Paystub` setiap Employee. Totalnya menjadi dasar laporan BPJS dan PPh 21.

## Pertanyaan umum

**Mengapa `Calculate` tidak bisa diklik?** Payroll mungkin nonaktif, Anda belum punya izin Edit, atau periode sudah `Locked`.

**Bisakah periode dihapus?** Hanya periode `Open` yang belum memiliki slip yang dibuat.

## Topik terkait

- [[bpjs]]
- [[pph21]]
- [[my-paystub]]
- [[employee-data]]
- [[workflow-finance]]

<!-- sources: routes/finance.php, app/Http/Controllers/Finance/PayrollController.php, app/Http/Controllers/Finance/PayrollSettingsController.php, app/Services/Payroll/PayrollPeriodService.php, app/Support/Payroll/PayrollPeriodRules.php, resources/views/finance/payroll/*.blade.php -->

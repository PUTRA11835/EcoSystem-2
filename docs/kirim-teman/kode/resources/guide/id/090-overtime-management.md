---
key: overtime-management
chapter: human-capital
order: 90
title: Pengelolaan Overtime
summary: Tinjau permintaan Overtime terhadap Attendance, setujui atau tolak, Export, dan atur aturan Overtime.
requires: [general.overtime, general.settings.overtime]
updated: 2026-10-27
---
`Overtime Management` mendaftar semua permintaan Overtime, menampilkannya berdampingan dengan catatan Attendance, dan memungkinkan Approver memutuskan. Tab `Settings` berisi aturan yang harus dipatuhi Employee saat mengajukan.

## Siapa yang menggunakan

Staf HR dan Approver yang ditetapkan dalam alur persetujuan. Employee mengajukan Overtime di My Overtime.

## Sebelum Anda mulai

Buka `Human Capital` › `Overtime Management`. Ada dua tab, `Overtime Management` dan `Settings`. Langkah persetujuan itu sendiri (siapa yang menyetujui dan urutannya) diatur di alur persetujuan Overtime, lihat [[approval-workflows]].

## Langkah demi langkah

### Mencari permintaan

1. Pilih periode cepat: `This week`, `This month`, atau `This year`, atau atur `From` dan `To`.
2. Filter berdasarkan `Search` (nomor permintaan, Employee, atau alasan), `Status` (`All`, `Pending`, `Approved`, `Rejected`), dan `Scope` (`Everyone` atau `Waiting for me`).
3. Pilih `Apply`.
4. Baca kartu: `Waiting for You`, `Pending Total`, `Approved`, `Rejected`, dan `Approved Hours`.

### Memutuskan permintaan

1. Di tabel, baca `Request`, `Employee`, `Date`, `Time`, `Claimed`, dan `Attendance`. Kolom Attendance membandingkan jam yang diklaim dengan Check-in dan Check-out hari itu, dan menampilkan `no record` atau `not yet` bila tidak ada yang dapat dibandingkan.
2. Baca `Reason` dan kolom `Approval`. Permintaan yang menunggu Anda ditandai `Your turn`. Jika menunggu orang lain, kolom tersebut menyebutkan siapa.
3. Ketik catatan. Catatan wajib diisi saat Anda menolak.
4. Pilih `Approve` atau `Reject`.

Jika alur persetujuan memiliki lebih dari satu langkah, permintaan diteruskan ke Approver berikutnya setelah Anda menyetujui.

### Export ke Excel

1. Pilih `Export Excel`.
2. Pilih apa yang sedang tampil di layar, periode cepat, atau `Choose a period…`.
3. Di dialog, pilih `Period` (bulan, minggu, tahun, atau rentang tanggal) dan `Status`.
4. Konfirmasi untuk mengunduh berkasnya.

### Mengubah aturan Overtime

Di `Settings` (`Overtime Rules`), Anda dapat mengatur:

- Tanggal: apakah tanggal mendatang diperbolehkan, batas mundur dalam hari (0 berarti tanpa batas), dan periode pelaporan yang sudah ditutup.
- Durasi: apakah Overtime boleh berakhir lewat tengah malam, durasi minimum dalam menit, serta batas harian dan mingguan (0 berarti tanpa batas). Durasi minimum hanya diberi tanda, tidak diblokir.
- Persetujuan: persetujuan oleh diri sendiri, apakah Approver boleh menyesuaikan jam, dan Role Approver cadangan.
- Perbandingan dan alasan: toleransi selisih Attendance dalam menit dan panjang minimum alasan.

Pilih `Save Settings` setelah selesai.

> [!WARNING]
> Perubahan aturan berlaku untuk permintaan baru sejak Anda menyimpan. Konsultasikan dengan pimpinan HR sebelum mengaktifkan persetujuan oleh diri sendiri.

## Apa yang terjadi selanjutnya

Employee melihat keputusan di My Overtime. Jam yang disetujui dijumlahkan pada kartu `Approved Hours`.

## Pertanyaan umum

**Mengapa saya tidak bisa menyetujui?** Permintaan mungkin sedang menunggu Approver lain. Barisnya menyebutkan nama Approver.

**Mengapa Attendance tertulis no record?** Employee tidak memiliki Check-in pada tanggal tersebut.

## Topik terkait

- [[attendance-management]]
- [[approval-workflows]]
- [[leave-permit-management]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/overtime/review.blade.php, resources/views/hr-general/overtime/settings.blade.php, resources/views/hr-general/overtime/components/hub-tabs.blade.php -->

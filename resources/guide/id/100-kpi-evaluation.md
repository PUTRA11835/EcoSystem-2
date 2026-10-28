---
key: kpi-evaluation
chapter: human-capital
order: 100
title: KPI Evaluation
summary: Pantau cakupan KPI bulanan, buat dan tinjau penilaian, serta kelola Template penilaian.
requires: [general.kpi-evaluation, general.kpi-evaluation.list, general.kpi-evaluation.templates, general.kpi-evaluation.teams]
updated: 2026-10-27
---
`KPI Evaluation` adalah sisi HR dari KPI: melihat siapa yang sudah memiliki penilaian bulan ini, meninjau nilai dari atasan, menyetujui hasil, dan merawat Template penilaian.

## Siapa yang menggunakan

Staf HR. Employee dan atasan mengerjakan bagiannya sendiri di My KPI, lihat [[my-kpi]].

## Sebelum Anda mulai

Buka `Human Capital` › `KPI Evaluation`. Sesuai akses Anda, Anda melihat dashboard, daftar penilaian, `Manage Templates`, dan garis pelaporan `Lead & Project`. Tombol untuk membuat, menyetujui, atau Export hanya tampil bila Anda punya izin tersebut.

## Langkah demi langkah

### Membaca dashboard

1. Pilih bulan pada pemilih periode di bagian atas.
2. Baca kartu: `Visible Employees`, `Not Created`, `Draft`, `Submitted`, dan `Approved`.
3. Gunakan ikon corong pada `Employee`, `Position`, `Supervisor`, `Template`, dan `Status` untuk menyaring daftar.
4. Pilih `Export` untuk mengunduh data bulan itu sebagai berkas CSV.

### Membuat penilaian

1. Untuk Employee yang berstatus `Not Created`, buat penilaian dan pilih Template-nya.
2. Atau pilih `Re-sync` untuk mencocokkan semua orang dengan Template yang menargetkan mereka pada periode tersebut.

HR juga dapat mengubah batas waktu atau Template sebuah penilaian, dan menghapus penilaian yang masih draft atau yang ditolak.

### Meninjau dan menyetujui

1. Buka sebuah penilaian untuk melihat Employee, Template, indikator, dan bobotnya.
2. Atasan memberi nilai pada tiap indikator; HR dapat membaca jawaban, hasil aktual, dan nilai akhir. Nilai berbobot tiap indikator adalah nilai dibagi batas atas skala, dikalikan bobot indikator.
3. Jika nilai belum dapat diterima, pilih `Request Revision` dan tulis apa yang harus diubah (wajib).
4. Jika sudah, pilih `Approve Evaluation`, tambahkan catatan HR (opsional), lalu konfirmasi. Setelah disetujui, hasilnya terlihat oleh Employee.

Penilaian bergerak melalui `Draft`, `Self-Assessed`, `Reviewed`, `Completed`, lalu `Approved`, atau `Needs Revision` bila HR mengembalikannya. HR hanya dapat menyetujui setelah penilaian diri dan penilaian atasan selesai.

Penilaian diri ditampilkan sebagai view only: HR tidak menilai atau menyetujuinya. Pada penilaian ke atas (upward), identitas penilai tetap anonim dan atasan hanya melihat rata-rata yang dipublikasikan.

### Mengelola Template penilaian

1. Pilih `Manage Templates`.
2. Filter berdasarkan jenis: `Self-Assessment`, `Lead Assessment`, `Peer Assessment`, atau `Upward Assessment`.
3. Pilih `New Template` atau ubah yang sudah ada pada formulir satu halaman penuh, lalu simpan.

### Memeriksa garis pelaporan

`Lead & Project` menampilkan Project dan atasan tiap Employee. Halaman ini hanya baca: atasan berasal dari manajer Project atau dari atasan langsung pada data Employee, jadi ubah di [[employee-data]].

## Apa yang terjadi selanjutnya

Employee membuka hasil yang disetujui di My KPI.

## Pertanyaan umum

**Mengapa seorang Employee tidak tercantum?** Daftar hanya menampilkan Employee yang boleh Anda lihat.

## Topik terkait

- [[my-kpi]]
- [[employee-data]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/kpi/dashboard.blade.php, resources/views/hr-general/kpi/review.blade.php, resources/views/hr-general/kpi/templates.blade.php, resources/views/hr-general/kpi/lead-project.blade.php, app/Models/KpiEvaluation.php -->

---
key: my-kpi
chapter: my-workspace
order: 90
title: My KPI
summary: Isi self-assessment bulanan Anda, dan nilai anggota tim bila Anda seorang lead atau penilai rekan.
requires: [general.my-kpi]
updated: 2026-10-27
---
`My KPI` adalah tempat Anda menyelesaikan penilaian KPI yang ditugaskan kepada Anda dan melihat hasil yang disetujui HR. Tampilannya bergantung pada penugasan Anda: Anda mungkin hanya memiliki self-assessment, atau juga penilaian untuk tim Anda.

## Siapa yang menggunakan

Setiap Employee dengan menu `My KPI`. Lead, rekan, dan anggota tim melihat tab tambahan bila mereka memiliki penilaian untuk diberikan atau diterima.

## Sebelum Anda mulai

Lihat kartu di bagian atas: `Current Period`, `Avg Score` (dari evaluasi yang disetujui), `Total Evals`, dan `Pending Assessments` (perlu tindakan). Catat batas waktu setiap penilaian.

## Langkah demi langkah

### Mengisi self-assessment

1. Buka `My KPI` dari sidebar dan pilih tab `Self-Assessment`.
2. Jika kotak kuning `Fill Self-Assessment Now` muncul, pilih tombolnya. Jika tidak, temukan periodenya di `Monthly Self-Assessments`.
3. Periksa `Period`, `Template`, `Score`, dan `Deadline` pada tabel. Penilaian yang terlambat ditandai `Overdue`.
4. Pilih `Fill Self-Assessment`, beri nilai pada setiap indikator, dan tambahkan komentar bila formulir memintanya.
5. Kirim formulir. `Status` berubah menjadi `Self-Assessed` dan kolom `Action` kemudian menampilkan `View Details`.

### Merevisi setelah HR mengembalikan

1. Jika `Status` menampilkan `Needs Revision`, pilih `Revise`.
2. Perbarui jawaban Anda lalu kirim kembali.

### Menilai tim (untuk lead)

1. Pilih tab `Lead Assessment`.
2. Pada tabel `Team Member`, `Position`, `Template`, `Status`, `Score`, temukan orangnya lalu pilih `Evaluate`. Gunakan `Revise` bila perlu revisi dan `View` setelah terkirim.
3. Hasil kerja sebelumnya tersimpan di `My Review History`, dikelompokkan per bulan.

### Memberi umpan balik rekan atau ke atasan

1. Pilih `My Team (Peer)` untuk menilai rekan yang diminta Anda nilai pada periode ini.
2. Pilih `Upward Assessment` untuk memberi umpan balik tentang lead Anda. Kiriman Anda tampil di `My Upward Submissions`.
3. Umpan balik tentang Anda tampil di `Peer Assessments About Me` dan `Feedback From My Team` setelah dipublikasikan, lengkap dengan jumlah penilai dan skor rata-rata.

## Apa yang terjadi selanjutnya

Sebuah evaluasi lengkap bila self-assessment Anda dan penilaian lead sama-sama selesai. HR kemudian menyetujuinya, dan skornya dihitung dalam `Avg Score`. Status yang mungkin adalah `Draft`, `Self-Assessed`, `Reviewed`, `Completed`, `Approved`, dan `Needs Revision`. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Saya tidak melihat template.** `No self-assessment templates have been assigned to you yet.` HR belum menugaskan template kepada Anda untuk periode ini.

**Ada tab yang tidak terlihat.** Tab hanya muncul bila Anda memiliki penugasan atau role yang sesuai.

## Topik terkait

- [[statuses-and-approvals]]
- [[my-approvals]]
- [[access-and-menus]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyKpiController.php, resources/views/hr-general/kpi/my-kpi.blade.php, app/Models/KpiEvaluation.php -->

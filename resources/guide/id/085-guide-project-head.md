---
key: guide-project-head
chapter: reference
order: 85
title: Panduan Delivery Project Head
summary: Yang dijalankan dan dipantau project head: proyek delivery, beban kerja konsultan, laporan, SLA, dan bagian proyek di setiap periode pelaporan.
requires: ["@role:Delivery Project Head"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Project Head. Anda mengawasi proyek delivery dan orang-orang yang mengerjakannya.

## Siapa yang menggunakan

Delivery Project Head. Menu Anda mencakup `Delivery` › `Project` dan `Support`, `Consultant Workload`, beberapa halaman `Reporting`, `SLA` › `SLA Report`, `Master` (`Employee` dan `Business Partner`) dan `RPMO` › `Period Management`. Sebagian poin mungkin tidak berlaku bila administrator mengubah akses Anda.

## Sebelum Anda mulai

- Data proyek memuat pendapatan, biaya, dan kontak customer. Pakai hanya untuk pekerjaan Anda dan jangan menyalinnya ke chat atau email.
- Jangan pernah memasukkan kredensial atau password customer ke catatan atau dokumen proyek.

## Langkah demi langkah

### Kelola proyek delivery

![Halaman Project Delivery dengan kartu ringkasan dan daftar proyek](images/guide/delivery-project-list.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Delivery` › `Project`. Kartu di atas menghitung proyek menurut status dan jadwal: `Total Projects`, `Open`, `In Process`, `Closed`, `On Track`, `At Risk`, `At Critical` dan `Ending ≤ 30 days`. Klik sebuah kartu untuk menyaring daftar.
2. Pakai `Add New` untuk membuat proyek, atau `Export Excel` untuk mengunduh daftar. Pilih satu baris untuk membukanya.

![Halaman detail proyek dengan tab dan tombol Create Folder, Close Project dan Delete Project](images/guide/delivery-project-detail.png)

*Tangkapan layar dengan data contoh.*

3. Di dalam proyek, kerjakan tab-tabnya: `General`, `Delivery Info`, `Team`, `Documents`, `Issues`, `Risks`, `Location`, `Planning`, `WRICEF` dan `Plan Cost`.
4. Pakai `Create Folder` untuk membuat folder OneDrive proyek sebelum Anda mengunggah dokumen.
5. Catat masalah di `Issues` dengan `Add Issue`, dan risiko di `Risks` dengan `Add Risk`.
6. Pakai `Close Project` saat pekerjaan selesai. `Delete Project` menghapus proyek, jadi pakai hanya untuk proyek yang dibuat karena salah.

### Pantau orangnya

1. Buka `Consultant Workload` sebelum merencanakan pekerjaan, untuk melihat siapa yang sibuk.
2. Buka `Reporting` › `Consultant Assignment` untuk melihat penempatan setiap konsultan.

### Baca laporan

Menu `Reporting` Anda berisi `MD Validation`, `MD Recap`, `Collection Outlook`, `Collection Outlook (Support)`, `Ticketing Overview`, `Ticket by Modul` dan `Consultant Assignment`. Periksa `SLA` › `SLA Report` untuk waktu respons dan penyelesaian.

### Setujui waktu

Di `Calendar` › `Timesheets`, tinjau waktu yang dicatat tim Anda lalu setujui atau tolak, bila role Anda mengizinkan. Lihat [[calendar-timesheet]].

### Periode pelaporan

1. Buka `RPMO` › `Period Management`.
2. Buka atau tutup bagian `Project Domain` pada sebuah periode untuk tim Anda, dan putuskan permintaan akses terlambat pada langkah pertama. Lihat [[period-management]].

## Apa yang terjadi selanjutnya

Item yang disetujui berlanjut dan pemintanya diberi tahu. Permintaan akses terlambat yang Anda setujui diteruskan ke RPMO untuk keputusan akhir.

## Pertanyaan umum

**Saya tidak melihat sebuah proyek.** Daftar menampilkan apa yang diizinkan oleh akses Anda. Tanyakan kepada administrator bila proyek itu seharusnya ada.

**Sebuah tombol tidak ada di dalam proyek.** Itu bukan bagian dari role Anda.

**Saya tidak dapat membuka bagian Support pada sebuah periode.** Bagian itu milik Support Head.

## Topik terkait

- [[period-management]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/delivery/project/projects/index.blade.php, resources/views/delivery/project, resources/views/reporting, resources/views/rpmo/periods/index.blade.php -->

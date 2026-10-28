---
key: delivery
chapter: work-service
order: 40
title: Delivery (Project & Support)
summary: Kelola Project delivery dan kontrak Support, beserta perencanaan, issue, risiko, dan biayanya.
requires: [delivery, delivery.project, delivery.support]
updated: 2026-10-27
---
`Delivery` terdiri dari dua bagian: `Project` untuk proyek implementasi, dan `Support` untuk kontrak dukungan berkelanjutan. Masing-masing memiliki daftar, halaman rincian, dan tampilan perencanaan.

## Siapa yang menggunakan

Project owner, kepala delivery, support manager, dan administrator. Tab, section, dan tombol yang tampil bergantung pada akses Anda; setiap section memiliki hak lihat, ubah, dan kelola yang terpisah.

## Sebelum Anda mulai

Siapkan customer, nomor IO, tanggal kontrak, dan project owner sebelum menambah item baru.

## Langkah demi langkah

### Melihat daftar Project

1. Buka `Work & Service` › `Delivery` › `Project`. Halamannya berjudul `Project Delivery`.
2. Baca kartu `Summary`: `Total Projects`, `Open`, `In Process`, `Closed`, `On Track`, `At Risk`, `At Critical`, dan `Ending ≤ 30 days`. Klik sebuah kartu untuk menyaring daftar.
3. Ketik di kotak pencarian untuk mencari Project menurut nomor IO, customer, tipe, nama, AE, atau owner.
4. Gunakan tab `Project`, `Issues`, dan `Planning` di bagian atas untuk berpindah antara daftar, seluruh issue Project, dan perencanaan Project.

### Menambah Project

1. Klik `Add New`.
2. Pilih `Customer/Client`, `Project Owner`, dan `Project Type`.
3. Pilih `High Level Risk` (`Low`, `Moderate`, atau `High`).
4. Isi `Contract Start Date`, `Contract End Date`, `IO/Number Order`, `Project Name`, dan `Description`.
5. Klik `Save Project`. Tombol `Add New` hanya tampil bila Anda berhak menambah Project.

### Bekerja di dalam Project

1. Klik sebuah Project untuk membuka `Project Detail`.
2. Gunakan tab section: `General`, `Delivery Info`, `Team`, `Documents`, `Issues`, `Risks`, `Location`, `Planning`, `WRICEF`, dan `Plan Cost`. Anda hanya melihat section yang boleh Anda lihat.
3. Ubah section yang menyediakan opsi ubah, misalnya untuk menambah anggota tim, issue, risiko, atau item biaya.
4. Buka section `Planning` untuk menyusun fase dan aktivitas. Beralih antara `Table View`, `Gantt Chart`, dan `S-Curve`, dan gunakan `Export` untuk berkas PDF atau Excel.

### Menutup dan membuka kembali Project

1. Klik `Close Project` di bagian atas halaman rincian lalu konfirmasi.
2. Project menjadi hanya-baca sampai dibuka kembali.
3. Untuk mengubahnya lagi, klik `Reopen Project`.

> [!NOTE]
> Kedua tombol ini hanya tampil bagi orang yang berhak menutup Project.

### Mengelola kontrak Support

1. Buka `Work & Service` › `Delivery` › `Support`. Halamannya berjudul `Delivery Support`.
2. Gunakan kartu ringkasan (`Total Delivery Support`, `In Progress`, `Completed`, `Not Started`, `Ending ≤ 30 days`, `Past End Date`, `Upcoming`, `No Timeline`) dan filter kolom untuk mempersempit daftar.
3. Klik `Add Delivery Support` untuk membuat baru, bila Anda memiliki akses.
4. Klik sebuah item untuk membuka `Support Details` dengan tab `General`, `Approval`, `Financial`, `Team`, `Customer PIC`, `Activities`, `SLA`, dan `Plan Cost`.
5. Klik `Open Planning` untuk merencanakan fase dan aktivitas dalam tampilan `Table`, `Gantt`, atau `S-Curve`. `Configure Phases` mengatur fase yang ditampilkan.
6. Gunakan `Documents` dan `Deliverable Folder` untuk berkas, serta `Updates & Notes` untuk catatan progres.

## Apa yang terjadi selanjutnya

- Project berstatus `Closed` tidak dapat diubah sampai ada yang membukanya kembali.
- Tab `SLA` pada item Support terhubung dengan tingkat layanan yang dijelaskan di [[sla]].
- Rencana penagihan dari Project dan Support menjadi sumber [[report-delivery]].

## Pertanyaan umum

**Saya tidak bisa mengubah Project.** Project mungkin sudah ditutup, atau role Anda hanya memiliki akses lihat.

**Ada tab section yang tidak tampil.** Setiap section memerlukan izin lihatnya sendiri. Hubungi administrator.

## Topik terkait

- [[sla]]
- [[rpmo]]
- [[report-delivery]]
- [[tickets]]

<!-- sources: routes/web.php, routes/delivery-support.php, resources/views/delivery/project/projects/index.blade.php, resources/views/delivery/project/projects/show.blade.php, resources/views/delivery/project/projects/create.blade.php, resources/views/delivery/support/list/index.blade.php, resources/views/delivery/support/list/show.blade.php, resources/views/delivery/support/planning/index.blade.php -->

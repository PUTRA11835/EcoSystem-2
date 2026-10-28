---
key: my-tasks-workload
chapter: work-service
order: 30
title: My Tasks & Consultant Workload
summary: Lihat Ticket yang ditugaskan kepada Anda dan seberapa padat pekerjaan tiap Consultant.
requires: [ticket.my-tasks, ticket.consultant-workload]
updated: 2026-10-27
---
`My Tasks` menampilkan Ticket aktif yang ditugaskan kepada Anda. `Consultant Workload` menunjukkan beban kerja tiap Consultant sehingga manajer dapat menyeimbangkan penugasan.

## Siapa yang menggunakan

Consultant dan pengguna Delivery Support memakai `My Tasks`. Manajer, kepala bagian, dan koordinator memakai `Consultant Workload`. Setiap halaman hanya tampil di sidebar bila Anda memiliki aksesnya.

## Sebelum Anda mulai

Beban kerja dihitung dari mandays yang sudah disetujui pada Ticket. Ticket tanpa mandays yang disetujui tidak menambah hari yang dialokasikan. Lihat [[tickets]] untuk cara mengusulkan mandays.

## Langkah demi langkah

### Memeriksa tugas Anda

1. Buka `Work & Service` › `My Tasks`.
2. Pilih bulan dan tahun di bagian atas untuk mengganti periode.
3. Baca kartu ringkasan: `Active Tickets`, `Alloc MD`, `Remaining`, `Workload`, dan `Load Score`.
4. Ketik di kotak pencarian untuk mencari Ticket, subjek, atau customer.
5. Klik judul kolom `Status` untuk menyaring menurut status.
6. Periksa kolom tabel: `Ticket No.`, `Customer`, `Status`, `Priority`, `Module`, `Man Days`, `Workload (remain/alloc)`, `End Date`, dan `Progress`.

### Meninjau beban kerja Consultant

1. Buka `Work & Service` › `Consultant Workload`.
2. Klik `Group Modul` untuk menyaring menurut kelompok modul, dan `Reset` untuk menghapusnya.
3. Gunakan filter kolom untuk `Consultant` (nama atau ECI), `Personnel Sub Area`, `Current Assignment`, dan `Module`.
4. Klik `Expand All` atau `Collapse All` untuk membuka atau menutup daftar Ticket semua Consultant. Anda juga dapat membuka satu Consultant saja.
5. Klik judul kolom seperti `Tickets`, `Alloc Days`, `Remain`, `Workload`, atau `Load Score` untuk mengurutkan.

## Cara membaca angkanya

| Item | Arti |
|---|---|
| `Alloc Days` / `Alloc MD` | Mandays yang dialokasikan kepada Consultant pada Ticket aktif |
| `Remain` / `Remaining` | Mandays yang masih tersisa |
| `Workload` | Hari tersisa dibagi hari yang dialokasikan, ditampilkan sebagai bilah |
| `Load Score` | Hari tersisa dikalikan (1 + 0,1 per Ticket), sehingga banyak Ticket kecil terhitung lebih berat |

## Apa yang terjadi selanjutnya

Angka diperbarui setiap kali Anda membuka halaman atau mengganti bulan, tahun, atau filter. Seiring mandays terpakai, hari tersisa dan bilah workload ikut turun.

## Pertanyaan umum

**Ticket yang saya kerjakan tidak ada di `My Tasks`.** Hanya Ticket yang ditugaskan kepada Anda yang tampil. Minta ticket lead menambahkan Anda sebagai anggota tim pada Ticket tersebut.

**Mengapa `Load Score` berbeda dari `Workload`?** `Workload` membandingkan hari tersisa dengan hari yang dialokasikan. `Load Score` juga memperhitungkan jumlah Ticket yang ditangani.

## Topik terkait

- [[tickets]]
- [[ticket-validation]]
- [[sla]]

<!-- sources: routes/web.php, app/Http/Controllers/TaskController.php, app/Http/Controllers/ConsultantWorkloadController.php, resources/views/ticket/task.blade.php, resources/views/ticket/consultant-workload.blade.php -->

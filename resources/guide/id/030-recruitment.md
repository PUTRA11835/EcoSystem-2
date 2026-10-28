---
key: recruitment
chapter: human-capital
order: 30
title: Recruitment
summary: Buka lowongan, ikuti kandidat sepanjang seleksi, dan jadwalkan wawancara.
requires: [general.recruitment, general.recruitment.candidates, general.recruitment.jobs, general.recruitment.schedule, general.recruitment.settings]
updated: 2026-10-27
---
`Recruitment` adalah satu hub dengan lima tab: `Dashboard`, `Selection Process`, `Schedule`, `Job Openings`, dan `Settings`. Anda hanya melihat tab yang diizinkan untuk Role Anda.

## Siapa yang menggunakan

Recruiter HR dan pewawancara yang mengelola lowongan, kandidat, dan kalender wawancara.

## Sebelum Anda mulai

- Buka `Human Capital` › `Recruitment`. Sidebar membuka tab pertama yang boleh Anda gunakan.
- Lowongan dapat dikaitkan dengan departemen, posisi, recruiter, dan dokumen yang diminta dari pelamar. Pilihan dropdown berasal dari `Settings`.
- Tombol seperti `New Job Opening`, `Add Candidate`, dan `Schedule Interview` hanya tampil bila Anda punya izin Create pada tab tersebut.

## Langkah demi langkah

### Membuat lowongan

1. Buka `Job Openings` lalu pilih `New Job Opening`.
2. Di `Posting`, isi `Job Title`, `Platform`, `Posting Link`, `Opening Date & Time`, dan `Closing Date & Time`, lalu `Employee Type`, `Location`, `Department`, `Position`, `Description`, dan `Requirements`.
3. Centang `Documents Requested From Applicants`.
4. Di `Internal Information`, atur `Positions Needed`, kisaran gaji, dan `Recruiter`.
5. Simpan formulir.

Daftar menampilkan status `Scheduled`, `Open`, atau `Closed`. Status mengikuti tanggal buka dan tutup secara otomatis. Gunakan `Close now` untuk menutup lowongan lebih awal; gunakan `View`, `Edit`, atau `Delete` sesuai kebutuhan.

### Menambah kandidat

1. Buka `Selection Process` lalu pilih `Add Candidate`.
2. Isi `Candidate Name`, `Position`, `Job Opening` dan `Source` (opsional), serta email dan telepon.
3. Simpan. Kandidat memulai tahap seleksi.

Buka kandidat untuk melihat `Candidate Details`, mengunggah `Documents`, menambah catatan internal, dan membaca `Selection Timeline`.

### Memindahkan kandidat sepanjang seleksi

1. Buka kandidat dan ubah `Status`: `HR Interview`, `User Interview`, `Technical Test`, `Offer`, atau `Rejected`.
2. Untuk status wawancara, jadwalkan wawancara agar muncul di kalender.
3. Pilih `Offer` bila kandidat lolos. Kandidat tersebut kemudian dapat dipilih saat menulis Offering Letter, lihat [[offering-letter]].

Status `Hired` diatur otomatis ketika kandidat menerima penawaran dan akun Employee dibuat.

### Menjadwalkan wawancara

1. Buka `Schedule` lalu pilih `Schedule Interview`.
2. Pilih kandidat, `Stage`, `Date`, `Start Time`, `End Time`, pewawancara, dan `Meeting Link` (opsional).
3. Simpan. Gunakan tampilan hari, minggu, atau bulan untuk membaca kalender. Wawancara dapat dijadwalkan ulang, dibatalkan, atau disinkronkan dari kartunya.

### Mengatur daftar dropdown dan kalender

`Settings` berisi `Calendar Source` (sistem ini atau akun Microsoft), zona waktu, dan `Dropdown Lists` seperti platform dan sumber. Ketik opsi baru lalu tekan Enter untuk menambahkannya.

## Apa yang terjadi selanjutnya

Kandidat pada tahap `Offer` ditawarkan melalui `Offering Letter`. Ketika mereka menerima, data Employee dibuat dan Onboarding dimulai, lihat [[onboarding]].

## Pertanyaan umum

**Mengapa saya tidak menemukan sebuah tab?** Tab mengikuti Role Anda. Hubungi administrator.

**Siapa yang melihat catatan internal?** Hanya tim recruitment.

## Topik terkait

- [[offering-letter]]
- [[onboarding]]
- [[employee-data]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/recruitment/index.blade.php, resources/views/hr-general/recruitment/jobs.blade.php, resources/views/hr-general/recruitment/job-form.blade.php, resources/views/hr-general/recruitment/candidates.blade.php, resources/views/hr-general/recruitment/candidate-show.blade.php, resources/views/hr-general/recruitment/schedule.blade.php, resources/views/hr-general/recruitment/settings.blade.php, app/Models/Recruitment/Candidate.php -->

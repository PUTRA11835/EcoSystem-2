---
key: my-calendar-timesheet
chapter: my-workspace
order: 110
title: Calendar dan My Timesheet
summary: Lihat acara perusahaan dan hari libur, kelola agenda pribadi, dan catat jam kerja Anda di My Timesheet.
requires: [calendar, calendar.events, calendar.timesheets]
updated: 2026-10-27
---
`Calendar` menampilkan acara perusahaan dan agenda pribadi Anda. `My Timesheet` adalah tempat Anda mencatat jam kerja dan mengirimkannya untuk persetujuan. Keduanya ada di bawah `My Workspace`.

## Siapa yang menggunakan

Setiap Employee yang melihat `Calendar` dan `My Timesheet`. Kepala tim dan Approver memakai halaman timesheet yang sama untuk memeriksa timesheet tim mereka.

## Langkah demi langkah

### Membaca kalender

1. Buka `Calendar` dari sidebar. Judul halamannya `Events Calendar`.
2. Pilih tampilan `Month`, `Week`, atau `Day`. Gunakan tombol panah untuk berpindah dan `Today` untuk kembali ke tanggal sekarang.
3. Pilih sebuah acara untuk membuka `Event Details`. Daftar `Event Types` menjelaskan warnanya.

Acara perusahaan dan hari libur ditetapkan oleh HR. Semuanya tampil di kalender setiap Employee.

### Menambahkan ke agenda Anda

1. Pilih `Add to My Agenda`. Orang yang boleh mempublikasikan acara perusahaan melihat `Create Event`.
2. Isi `Event Title`.
3. Pilih jenisnya: `Meeting`, `Task`, `Deadline`, `Urgent`, atau `Reminder`.
4. Pada `Visible to`, biarkan `Only me (my agenda)`. Acara untuk seluruh perusahaan hanya tersedia bagi orang yang boleh mempublikasikannya.
5. Tambahkan `Description`, nyalakan `All day event` bila perlu, lalu isi tanggal mulai serta `End Date`, `End Time`, dan `Location`.
6. Pilih `Save Event`.

Untuk mengubah atau menghapus acara, buka acaranya lalu pilih `Edit` atau `Delete`. Anda hanya dapat mengubah item agenda milik Anda sendiri.

### Mencatat jam kerja di My Timesheet

1. Buka `My Timesheet` dari sidebar.
2. Pilih `Create Timesheet`. Formulirnya berjudul `Log Working Hours`.
3. Pilih jenis (`Project`, `Support`, atau `Office`) dan isi tanggal serta jadwalnya.
4. Jelaskan pekerjaan pada `Description`. Tambahkan `Notes` bila perlu.
5. Simpan entri. Entri tetap berstatus `Draft`, dan masih dapat Anda edit.
6. Pilih satu atau beberapa entri pada tabel lalu gunakan tombol kirim, kemudian konfirmasi. Setelah dikirim, Anda tidak dapat mengeditnya sampai selesai diperiksa.

Gunakan tab `All`, `Project`, `Support`, dan `Office`, serta filter `Period`, `Status`, dan `Activity Type` untuk menemukan entri. `Export Timesheet` mengunduh daftar Anda.

### Meminta late access

Jika sebuah periode sudah ditutup dan Anda masih perlu mengirim:

1. Pilih `Request Late Access`.
2. Pilih periode pada `Select Period`.
3. Jelaskan alasannya pada `Reason / Notes`.
4. Pilih `Submit Request`. Head dan RPMO Anda memeriksanya, dan periode yang boleh Anda isi tercantum di dialog.

## Apa yang terjadi selanjutnya

Timesheet yang dikirim menuju orang yang menyetujuinya. Statusnya adalah `Draft`, `Submitted`, `Approved`, dan `Rejected`. Jika timesheet ditolak, perbaiki lalu kirim lagi. Anda menerima Notification untuk keputusan late access. Lihat [[statuses-and-approvals]].

## Pertanyaan umum

**Saya tidak dapat membuat timesheet untuk bulan lalu.** Periodenya sudah ditutup. Gunakan `Request Late Access`.

**Apakah orang lain dapat melihat agenda saya?** Item yang diatur ke `Only me (my agenda)` hanya terlihat oleh Anda.

## Topik terkait

- [[my-leave-permit]]
- [[my-attendance]]
- [[statuses-and-approvals]]

<!-- sources: routes/web.php, app/Http/Controllers/CalendarController.php, resources/views/calendar/events.blade.php, resources/views/calendar/timesheets.blade.php, resources/views/partials/sidebar.blade.php -->

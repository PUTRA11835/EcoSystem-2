---
key: guide-ds-head
chapter: reference
order: 81
title: Panduan Delivery Support Head
summary: Yang diputuskan dan dipantau kepala support: persetujuan, penugasan, SLA, laporan, dan periode pelaporan.
requires: ["@role:Delivery Support Head"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery Support Head. Anda mengawasi layanan support dan memutuskan hal-hal yang butuh persetujuan.

## Siapa yang menggunakan

Kepala Delivery Support, serta orang lain yang rolenya mencakup periode pelaporan atau laporan Resolution Days. Sebagian poin mungkin tidak berlaku untuk role Anda.

## Sebelum Anda mulai

- Keputusan Anda berdampak ke penagihan dan beban kerja tim. Baca rinciannya sebelum menyetujui, dan tulis alasan yang jelas saat menolak.
- Anda dapat melihat data sensitif customer dan karyawan. Pakai seperlunya dan jangan disalin ke chat atau email. Informasi credential customer pada ticket tidak boleh disalin ke tempat lain.

## Langkah demi langkah

### Keputusan yang menunggu Anda

1. **Resolution Days**: buka ticket berstatus `Pending Head` lalu setujui, atau tolak disertai alasan. Lihat [[ticket-mandays]].
2. **Timesheets**: di `Calendar` › `Timesheets`, setujui atau tolak entri tim Anda, juga beberapa sekaligus. Lihat [[calendar-timesheet]].
3. **Akses terlambat**: permintaan menunggu di `Pending Head` sebelum diteruskan ke RPMO.
4. **Proposal mandays**: tinjau, ubah, kirim ke customer, atau batalkan dari ticket.
5. **Ticket masuk**: di `Ticket Validation`, setujui permintaan yang sah dan tolak sisanya disertai alasan. Lihat [[ticket-validation]].

### Memantau pekerjaan

1. Buka `Consultant Workload` sebelum menugaskan pekerjaan, untuk melihat siapa yang sibuk.
2. Pakai tab `All Tickets` dan `My Tasks` untuk mengikuti ticket yang tersendat.
3. Baca laporan di `Reporting`: `Resolution Days`, `MD Recap`, `MD Validation`, `Ticketing Overview`, `Log Shifting`, dan `Weekly Consolidation`.
4. Periksa `SLA` › `SLA Report` untuk waktu respons dan penyelesaian.

### Mengelola Delivery Support

![Halaman Delivery Support dengan kartu ringkasan dan daftar item support](images/guide/delivery-support-list.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Delivery` › `Support`.
2. Kerjakan bagian yang boleh Anda ubah: informasi umum, persetujuan, keuangan, tim, customer PIC, aktivitas, SLA, biaya rencana, dokumen, dan recons.
3. Tambahkan Delivery Support baru atau hapus yang tidak terpakai, bila role Anda mengizinkan.

### Periode pelaporan

1. Buka `RPMO` › `Period Management`.
2. Buka atau tutup bagian support dari sebuah periode untuk tim Anda, dan putuskan permintaan akses terlambat. Lihat [[period-management]].

## Apa yang terjadi selanjutnya

Item yang disetujui berpindah ke langkah berikutnya, dan pengaju diberi tahu. Item yang ditolak kembali kepada pengaju beserta alasan Anda.

## Pertanyaan umum

**Sebuah ticket tersendat di `Pending Head`.** Ticket itu menunggu Anda. Buka dan putuskan.

**Saya tidak melihat tombol yang dijelaskan di sini.** Itu bukan bagian role Anda. Hubungi administrator.

## Topik terkait

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/calendar/timesheets.blade.php, resources/views/rpmo/periods/index.blade.php, resources/views/reporting/reporting.blade.php -->

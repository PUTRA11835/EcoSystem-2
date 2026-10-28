# User Guide — panduan menulis (WAJIB dibaca penulis)

Berkas yang namanya diawali `_` (seperti ini) tidak dimuat sebagai topik. Isi panduan = satu berkas Markdown per topik, **dua bahasa**:

```
resources/guide/en/NNN-key.md      ← Inggris (ditulis dulu, sumber kebenaran)
resources/guide/id/NNN-key.md      ← Indonesia (nama berkas SAMA persis)
```
`NNN` = urutan 3 digit (010, 020, …) dalam bab; `key` = pengenal topik (huruf kecil, angka, tanda hubung) dan SAMA di kedua bahasa.

## 1. Front matter (awal berkas, wajib)

```yaml
---
key: my-leave-permit
chapter: my-workspace          # salah satu: getting-started, my-workspace, human-capital, external, finance, work-service, business-legal, reporting, administration, reference
order: 40                      # urutan dalam bab (kelipatan 10)
title: Leave & Permit          # judul topik (EN: bahasa Inggris; ID: judul dalam bahasa Indonesia, nama menu tetap Inggris)
summary: One sentence on what the user can do here.   # 1 kalimat, ≤ 140 karakter
requires: [ess.my_leave_permit]    # slug izin menu (cukup salah satu). [] = semua pengguna. HANYA slug dari resources/guide/_slugs.txt
updated: 2026-10-27            # tanggal terakhir isi diperiksa/diubah (Y-m-d). Ubah setiap kali isi berubah.
---
```

`requires`: boleh juga memuat izin semu `"@approver"` (ditunjuk sebagai penyetuju di alur persetujuan; menu My Approvals tampil untuk mereka). Pilih slug menu yang membuat orang itu *melihat menu/halaman tersebut di sidebar*. Pengguna yang tidak punya satu pun slug itu **tidak akan melihat topiknya**. Topik umum (mis. cara memakai aplikasi) memakai `requires: []`.

## 2. Kerangka isi topik (urut; hilangkan bagian yang tidak relevan, jangan menambah isi kosong)

| EN | ID |
|---|---|
| `## Who uses this` | `## Siapa yang menggunakan` |
| `## Before you start` | `## Sebelum Anda mulai` |
| `## Step by step` (dengan `###` per tugas) | `## Langkah demi langkah` |
| `## What happens next` | `## Apa yang terjadi selanjutnya` |
| `## Common questions` | `## Pertanyaan umum` |
| `## Related topics` | `## Topik terkait` |

- Awali dengan 1–2 kalimat tujuan (tanpa judul) sebelum `## Who uses this`.
- Langkah = daftar bernomor (`1.` `2.`), **satu tindakan per langkah**, kalimat perintah. Sertakan *hasil yang tampak* bila membantu ("Status berubah menjadi `Submitted`.").
- Topik ringkas: 250–700 kata. Mudah dipindai. Jangan menyalin isi layar mentah-mentah.
- Topik terkait: `[[other-key]]` atau `[[other-key|teks tautan]]` (key harus ada). Maksimal 5.

## 3. Penulisan elemen layar

- **Nama menu, tombol, tab, kolom, status** ditulis PERSIS seperti di layar (kapitalisasi dan ejaan sama) dan dibungkus tanda kutip balik: `` `Submit` ``, `` `Leave & Permit` ``. Pada panduan Indonesia **tetap bahasa Inggris** karena itu yang dilihat pengguna di layar.
- Jalur menu: `` `Human Capital` › `Onboarding` `` (tanda ›).
- Nilai yang diketik pengguna: pakai contoh wajar di dalam kalimat, bukan data nyata.
- Catatan khusus (jarang, hanya bila benar-benar membantu):

```
> [!NOTE]
> Informasi tambahan.

> [!TIP]
> Cara lebih cepat / praktik baik.

> [!IMPORTANT]
> Hal yang akan membuat pekerjaan gagal bila terlewat.

> [!WARNING]
> Tindakan yang tidak bisa dibatalkan / berdampak ke data orang lain.
```
- Tabel Markdown boleh untuk perbandingan/daftar status (maks. 5 kolom).
- Dilarang: HTML mentah, gambar, tautan keluar, alamat server, kata sandi/kredensial, istilah teknis internal (nama tabel, slug, nama kelas, SQL).

## 4. KEAKURATAN — aturan paling penting

1. Tulis HANYA yang benar-benar ada di aplikasi. **Baca kodenya**: rute (`routes/*.php`), pengendali (`app/Http/Controllers`), tampilan (`resources/views`), aturan di service/model. Jangan menebak dan jangan menyalin dari produk lain.
2. Nama menu/tombol diambil dari tampilan (`resources/views/...`) dan sidebar (`resources/views/partials/sidebar.blade.php`).
3. Izin: hanya jelaskan apa yang bisa dilakukan sesuai aturan di kode (mis. tombol hanya tampil bila punya izin Create). Katakan "bila Anda tidak melihat tombolnya, hubungi administrator untuk izin akses" — jangan menyebut slug.
4. Aturan bisnis (batas, status, siapa menyetujui) harus dari kode/konfigurasi. Bila ragu → **tulis lebih umum atau hilangkan**, lalu catat keraguan di laporan akhir Anda.
5. Di akhir setiap berkas EN tambahkan komentar sumber (tidak tampil di panduan):
   `<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/OvertimeController.php, resources/views/hr-general/overtime/... -->`
   Berkas ID memakai komentar yang sama.
6. Jangan menjanjikan fitur yang "akan ada". Jangan mengubah kode aplikasi.

## 5. Gaya bahasa

**English** — plain, professional, friendly. Address the reader as "you". Active voice, present tense, short sentences. Start steps with a verb ("Open", "Select", "Enter", "Click"). No marketing language, no "simply/just".

**Bahasa Indonesia** — profesional dan ramah; sapa pembaca dengan "Anda". Kalimat singkat, aktif, urutan sama dengan versi Inggris. **Jangan menerjemahkan paksa** istilah yang memang dipakai apa adanya dalam bahasa Inggris di lingkungan kerja/aplikasi (lihat daftar di bawah). Tulis istilah itu seperti di layar, tanpa tanda kutip miring; jika perlu penjelasan, beri satu kalimat pertama saja.

Kata kerja umum (ID): Buka, Pilih, Klik, Isi, Masukkan, Simpan, Kirim (`Submit`), Periksa, Unggah, Unduh.

### Istilah yang TETAP bahasa Inggris di panduan Indonesia
Dashboard, My Workspace, My Profile, Settings, Role, Menu Access, Employee, Onboarding, Offboarding, Recruitment, Offering Letter, Contract, Template, Payroll, Paystub, BPJS, PPh 21, Timesheet, Attendance, Check-in, Check-out, Leave & Permit, Overtime, Reimbursement, Cash Advance, Cash Advance Report, Purchase Request, Purchase Order, KPI, Approval, Approver, Request, Draft, Submitted, Approved, Rejected, Pending, Notification, Announcement, Favorite, Filter, Export, Import, Upload, Download, Save, Submit, Cancel, Search, Backup, Audit Log, Ticket, SLA, Project, Invoice, Vendor, ESS, HR, GA, PDF, Template, Inventory, Asset, Consultant, External.

### Terjemahan yang dianjurkan (kata biasa)
approval = persetujuan (kalimat), approve = menyetujui; reject = menolak; request = permintaan/pengajuan (boleh tetap Request bila nama menu); attachment = lampiran; supervisor/manager = atasan langsung; notes = catatan; period = periode; balance = saldo; deadline = batas waktu.

## 6. Alur kerja penulis
1. Baca `_PLAN.md` (daftar topik bagian Anda, key, bab, urutan, `requires`).
2. Per topik: baca kode → tulis berkas **EN** → tulis berkas **ID** (nama berkas sama).
3. Jalankan `php docs/HRGA/tools/guide-check.php` (bila tersedia) dan perbaiki temuan untuk berkas Anda.
4. Laporan akhir (ringkas, ≤ 40 baris): topik yang selesai, hal yang Anda ragukan/dihilangkan, fitur yang ditemukan tapi tidak tercakup.

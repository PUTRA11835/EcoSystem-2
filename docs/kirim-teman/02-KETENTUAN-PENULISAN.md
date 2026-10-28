# 02 — Ketentuan penulisan isi User Guide (WAJIB diikuti)

Ketentuan ini berlaku untuk **semua** yang menulis atau mengubah topik di kedua website agar isi tetap seragam. Salinan operasional yang dipakai penulis ada di `kode/resources/guide/_AUTHORING.md` (isinya sejalan dengan dokumen ini).

## 1. Struktur berkas
```
resources/guide/en/NNN-key.md     ← Inggris (ditulis dulu)
resources/guide/id/NNN-key.md     ← Indonesia (nama berkas SAMA persis)
```
- `NNN` = urutan 3 digit sesuai `order` (010, 020, …); `key` = huruf kecil, angka, tanda hubung; **sama di kedua bahasa dan unik di seluruh panduan**.
- Berkas berawalan `_` tidak dimuat sebagai topik (dokumen panduan menulis, daftar slug).

## 2. Front matter (wajib, di awal berkas)
```yaml
---
key: my-leave-permit
chapter: my-workspace          # harus ada di config/user_guide.php
order: 40                      # urutan dalam bab (kelipatan 10)
title: Leave & Permit          # EN: judul Inggris. ID: judul Indonesia (nama menu tetap Inggris)
summary: One sentence on what the user can do here.    # ≤ 140 karakter
requires: [ess.my_leave_permit]  # izin menu; [] = semua; boleh "@approver"
updated: 2026-10-27            # Y-m-d; ubah setiap isi berubah
---
```
- `requires`: izin yang membuat orang **melihat menu itu**. Cukup salah satu. Hanya slug yang benar-benar ada pada website itu.
- Pembaca front matter hanya mendukung: `kunci: nilai`, nilai teks (boleh dikutip), angka, daftar sebaris `[a, "b"]`. **Jangan** memakai YAML bersarang/multi-baris.

## 3. Kerangka isi (urut; hapus bagian yang tidak relevan)
| EN | ID |
|---|---|
| (1–2 kalimat tujuan, tanpa judul) | (sama) |
| `## Who uses this` | `## Siapa yang menggunakan` |
| `## Before you start` | `## Sebelum Anda mulai` |
| `## Step by step` (`###` per tugas) | `## Langkah demi langkah` |
| `## What happens next` | `## Apa yang terjadi selanjutnya` |
| `## Common questions` | `## Pertanyaan umum` |
| `## Related topics` | `## Topik terkait` |

- Langkah = daftar bernomor, **satu tindakan per langkah**, kalimat perintah; sebut hasil yang tampak bila membantu.
- Panjang 250–700 kata (topik besar boleh ±900). Jumlah judul `##` di EN dan ID harus sama.
- Topik terkait maksimal 5, memakai `[[key]]` atau `[[key|teks]]` (key harus ada).
- Tutup berkas dengan komentar sumber: `<!-- sources: routes/..., app/Http/Controllers/..., resources/views/... -->` (tidak tampil; dipakai peninjau untuk menelusuri keakuratan).

## 4. Elemen penulisan
- **Nama menu, tombol, tab, kolom, status** → persis seperti di layar dan dibungkus tanda kutip balik: `` `Submit` ``. **Tetap bahasa Inggris di panduan Indonesia.**
- Jalur menu: `` `Human Capital` › `Onboarding` ``.
- Kotak catatan (jarang, hanya bila membantu):
  `> [!NOTE]` informasi tambahan · `> [!TIP]` cara lebih cepat · `> [!IMPORTANT]` hal yang membuat pekerjaan gagal bila terlewat · `> [!WARNING]` tindakan yang tidak bisa dibatalkan / berdampak ke orang lain.
- Tabel Markdown boleh (maks. 5 kolom).
- **Dilarang:** HTML mentah, gambar, tautan keluar/alamat server, kata sandi/kredensial, istilah teknis internal (nama tabel, slug, kelas, SQL).
- Teks tampilan tetap (judul halaman, tombol, label) ada di `config/user_guide.php` → `ui.en` / `ui.id`. Ubah di sana, bukan di topik.
- Label yang disebut di topik tentang **panduan itu sendiri** mengikuti teks tampilan bahasanya (mis. di panduan Indonesia: `Daftar Isi`, `Unduh PDF`; di Inggris: `Table of Contents`, `Download PDF`).

## 5. Keakuratan (aturan terpenting)
1. Tulis **hanya yang benar-benar ada** di aplikasi. Baca kode: rute, pengendali, tampilan, aturan di service/model. Jangan menebak, jangan menyalin dari produk lain.
2. Nama menu/tombol diambil dari tampilan (view) dan sidebar — bukan dari ingatan.
3. Soal izin: jelaskan perilaku ("tombol hanya tampil bila Anda punya izin Create"); jangan menyebut slug. Arahkan ke administrator untuk meminta akses.
4. Aturan bisnis (batas, status, siapa menyetujui) harus dari kode/konfigurasi. Ragu → tulis lebih umum atau hapus, lalu catat di laporan/PR.
5. Jangan menjanjikan fitur yang "akan ada". Fitur "coming soon" tidak ditulis.
6. Saat fitur berubah → ubah topik terkait **di kedua bahasa**, naikkan `updated`, dan tambahkan baris di topik `whats-new` bila berguna bagi pengguna.

## 6. Gaya bahasa
**English** — plain, professional, friendly; "you"; active voice; present tense; short sentences; start steps with a verb (Open, Select, Enter, Click). No marketing language, no "simply/just".

**Bahasa Indonesia** — profesional dan ramah; sapa dengan **"Anda"**; kalimat singkat dan aktif; urutan sama dengan versi Inggris. **Jangan menerjemahkan paksa** istilah yang memang dipakai apa adanya di lingkungan kerja/aplikasi (lihat daftar di bawah). Kata kerja umum: Buka, Pilih, Klik, Isi, Masukkan, Simpan, Kirim (`Submit`), Periksa, Unggah, Unduh.

### Istilah yang TETAP bahasa Inggris di panduan Indonesia
Dashboard, My Workspace, My Profile, Settings, Role, Menu Access, Employee, Onboarding, Offboarding, Recruitment, Offering Letter, Contract, Template, Payroll, Paystub, BPJS, PPh 21, Timesheet, Attendance, Check-in, Check-out, Leave & Permit, Overtime, Reimbursement, Cash Advance, Cash Advance Report, Purchase Request, Purchase Order, KPI, Approval, Approver, Request, Draft, Submitted, Approved, Rejected, Pending, Notification, Announcement, Favorite, Filter, Export, Import, Upload, Download, Save, Submit, Cancel, Search, Backup, Audit Log, Ticket, SLA, Project, Invoice, Vendor, ESS, HR, GA, PDF, Inventory, Asset, Consultant, External.
Untuk website lain: **tambahkan** istilah khasnya ke daftar ini (di dokumen ini dan `_AUTHORING.md`) agar konsisten.

### Padanan yang dianjurkan (kata biasa)
approval = persetujuan (kalimat), approve = menyetujui, reject = menolak, attachment = lampiran, supervisor/manager = atasan langsung, notes = catatan, period = periode, balance = saldo, deadline = batas waktu.

## 7. Bab (chapter)
Bab didefinisikan di `config/user_guide.php` (`chapters`): `id`, ikon, judul EN dan ID. **Urutan di config = urutan tampil.** Bab bernomor otomatis menurut yang tampil bagi pengguna.
- Menambah bab baru → tambahkan di config (judul EN **dan** ID) di posisi yang disepakati kedua tim.
- Nama bab memakai nama yang sama dengan bagian sidebar bila ada (mis. `Human Capital`).

## 8. Daftar periksa sebelum menyerahkan perubahan isi
- [ ] Berkas EN **dan** ID ada, nama sama, `key` sama, struktur judul sejajar.
- [ ] `requires` memakai izin yang ada; topik umum memakai `[]`.
- [ ] `updated` diperbarui (Y-m-d) di kedua berkas.
- [ ] Nama menu/tombol persis seperti layar, dalam tanda kutip balik, bahasa Inggris.
- [ ] Tidak ada HTML mentah, gambar, tautan keluar, slug/istilah teknis.
- [ ] Komentar `<!-- sources: ... -->` ada.
- [ ] Tautan `[[key]]` menuju topik yang ada.
- [ ] `php docs/HRGA/tools/guide-check.php` → **0 galat, 0 peringatan**.
- [ ] `php artisan test tests/Unit/Guide` lulus.
- [ ] Dibaca ulang di halaman `/user-guide` (kedua bahasa) dan satu PDF.

## 9. Aturan konsistensi antara dua tim
1. **Kepemilikan isi:** topik untuk fitur milik satu website ditulis dan dirawat tim website itu. Topik umum (`getting-started`, `reference`) dirawat bersama; perubahan di dalamnya dikabarkan ke tim lain.
2. **Key unik:** jangan memakai key yang sama untuk topik berbeda. Bila dua website memiliki fitur sejenis, beri awalan yang jelas (mis. `hr-leave-…`) atau sepakati satu topik bersama.
3. **Dua bahasa selalu lengkap** sebelum digabung ke cabang utama.
4. **Mesin dan isi dipisah:** berkas mesin (`app/Services/Guide`, `UserGuideController`, `config/user_guide.php` bagian `ui`, `resources/views/user-guide/*`) diubah hanya setelah disepakati dan **disalin ke kedua website**. Berkas isi (`resources/guide/**`) bebas diubah sesuai kepemilikan.
5. **Satu sumber istilah:** daftar istilah di bagian 6 adalah sumber tunggal; perubahan daftar dikabarkan.
6. Sebelum penggabungan cabang, jalankan pemeriksa dan uji (bagian 8) pada hasil gabungan.

## 10. Alur menambah topik baru (ringkas)
1. Tentukan `key`, bab, urutan, dan izin menu yang relevan.
2. Salin topik yang mirip sebagai kerangka.
3. Baca kode fitur → tulis EN → tulis ID → isi `updated` dan komentar sumber.
4. Jalankan pemeriksa dan uji → buka halaman, periksa kedua bahasa dan PDF.
5. Tambahkan baris di topik `whats-new` bila perubahan terasa bagi pengguna.

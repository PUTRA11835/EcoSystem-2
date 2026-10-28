# Paket User Guide — untuk digabungkan ke website Eclectic lain

Versi paket **1.0 · 28 Oktober 2026** · dibuat dari proyek EcoSystem-2 (branch `aldy_hr`).

## Untuk apa paket ini
Fitur **User Guide** (panduan pengguna dalam aplikasi) sudah jadi dan teruji di EcoSystem. Paket ini dikirim agar **fitur dan isinya tetap sama** ketika digabungkan dengan website Eclectic lain yang dikembangkan dengan fokus berbeda. Yang dijaga konsisten:

1. **Perilaku fitur** (tampilan, bahasa, daftar isi, pencarian, PDF, penyaringan akses, tanggal "Updated").
2. **Format dan gaya isi** (struktur topik, istilah, bahasa Indonesia/Inggris, penulisan nama menu).
3. **Cara menambah/mengubah topik** (satu berkas = satu topik, dua bahasa, pemeriksa otomatis).

## Isi paket
| Berkas / folder | Fungsi |
|---|---|
| `00-BACA-DULU.md` | Ini. Gambaran paket dan langkah cepat. |
| `01-PENJELASAN-FITUR.md` | **Apa** yang dilakukan fitur ini dan **mengapa** dirancang demikian. |
| `02-KETENTUAN-PENULISAN.md` | **Aturan wajib** menulis/mengubah isi panduan (format, gaya, istilah, keakuratan, daftar periksa). |
| `03-INTEGRASI-DAN-PENGGABUNGAN.md` | **Cara memasang** ke website lain: syarat, potongan kode untuk berkas yang sudah ada, titik yang perlu disesuaikan, aturan agar dua tim tidak bentrok. |
| `04-DAFTAR-TOPIK.md` | 67 topik EcoSystem (key, bab, izin, judul EN/ID, tanggal) — contoh bentuk dan gaya. |
| `kode/` | Salinan berkas **sesuai lokasinya di proyek** (mesin fitur + isi panduan + pemeriksa + uji). Salin ke akar proyek tujuan. |

## Dua cara memakai paket
- **A. Memasang fitur lengkap** di website lain → ikuti `03-INTEGRASI-DAN-PENGGABUNGAN.md`, lalu tulis topik untuk fitur website itu mengikuti `02-KETENTUAN-PENULISAN.md`.
- **B. Hanya mengikuti ketentuan isi** (fitur sudah ada) → baca `02-KETENTUAN-PENULISAN.md` saja.

## Langkah cepat (cara A)
1. Salin isi folder `kode/` ke akar proyek tujuan (jalur di dalamnya sudah sama dengan di proyek).
2. Tempel potongan kode dari `03-INTEGRASI-DAN-PENGGABUNGAN.md` ke **rute, sidebar, menu profil, dan ikon** (jangan menimpa berkas-berkas itu).
3. Sesuaikan 3 titik: sumber daftar izin pengguna, bahasa pengguna, dan tata letak halaman (tabel di dokumen 03).
4. Atur bab di `config/user_guide.php`; ganti/hapus topik EcoSystem yang tidak berlaku, tambahkan topik website Anda.
5. Jalankan `php docs/HRGA/tools/guide-check.php` (harus 0 galat) dan `php artisan test tests/Unit/Guide`.
6. `php artisan route:clear && php artisan config:clear`, buka `/user-guide`.

## Prinsip yang tidak boleh berubah tanpa sepakat bersama
- Isi disaring **di server**; topik yang tidak boleh dilihat tidak pernah dikirim (termasuk ke PDF).
- Nama menu/tombol/status ditulis **persis seperti di layar** dan tetap **bahasa Inggris** di panduan Indonesia.
- Setiap topik ada di **dua bahasa** dengan nama berkas dan `key` yang sama.
- Tanggal "Updated" berasal dari `updated` di topik (bukan waktu berkas).
- Jangan memakai `symfony/yaml` (hanya ada di lingkungan pengembangan; di produksi tidak terpasang).

## Pertanyaan?
Hubungi pengembang EcoSystem-2 (Reinaldy). Bila Anda mengubah **mesin** fitur (berkas di `app/`, `config/`, `resources/views/user-guide/`), kabari agar perubahan disamakan di kedua website (lihat bagian "Aturan kerja bersama" di dokumen 03).

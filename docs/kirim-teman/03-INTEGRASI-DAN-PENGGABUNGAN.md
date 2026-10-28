# 03 — Integrasi dan penggabungan ke website lain

## 1. Syarat
| Kebutuhan | Keterangan |
|---|---|
| Laravel 12, PHP 8.3 | Sama dengan EcoSystem-2. |
| `league/commonmark` ≥ 2.8 (dengan ekstensi Table & Strikethrough) | Sudah ikut Laravel; tidak perlu paket baru. |
| `barryvdh/laravel-dompdf` ^3.1 | Untuk PDF. |
| **Tanpa `symfony/yaml`** | Hanya dev-dependency; **jangan dipakai** (produksi tak punya). Front matter memakai `parseFrontMatter()` bawaan. |
| Tata letak `dashboard` dengan `@yield('title'|'page-title'|'page-subtitle'|'content')`, `@stack('styles'|'scripts')` | Lihat bagian 4 bila beda. |
| Tailwind (CDN/build) dan Font Awesome | Kelas utilitas dan ikon `fa-*` dipakai di tampilan. |
| Variabel CSS `--primary-color` | Warna aksen (bawaan `#991b1b` bila tidak ada). |
| Penyimpan sesi/cache Laravel | Cache hasil render 12 jam, otomatis tidak berlaku saat berkas panduan berubah. |

**Tidak ada migrasi dan tidak ada SQL.**

## 2. Langkah pemasangan
1. **Salin** seluruh isi folder `kode/` ke akar proyek tujuan. Jalurnya sudah sama dengan di proyek (`app/…`, `config/…`, `resources/…`, `tests/…`, `docs/HRGA/tools/…`).
   - Bila proyek tujuan sudah punya `config/user_guide.php` atau `resources/guide`, **gabungkan** (jangan timpa): lihat bagian 5.
2. **Tempel potongan kode** di bagian 3 ke berkas yang sudah ada (rute, sidebar, menu profil, ikon). Jangan menimpa berkas-berkas itu.
3. **Sesuaikan titik adaptasi** (bagian 4).
4. **Atur bab dan topik**: ubah `chapters` di `config/user_guide.php`; hapus/ganti topik EcoSystem yang tidak berlaku; tulis topik website Anda (`02-KETENTUAN-PENULISAN.md`).
5. **Periksa**: `php docs/HRGA/tools/guide-check.php` (harus 0 galat) dan `php artisan test tests/Unit/Guide`.
6. `php artisan route:clear && php artisan config:clear && php artisan view:clear`, lalu buka `/user-guide` (uji kedua bahasa, pencarian, daftar isi, PDF).
7. (Opsional) Uji browser: jalankan server, buat sesi uji, `node docs/HRGA/tools/e2e-user-guide.mjs <base> <cookie> <sid> <kata>` (Chrome, Node ≥ 22).

## 3. Potongan kode untuk berkas yang sudah ada

### a. Rute (`routes/web.php`) — di dalam grup yang sudah mewajibkan login
```php
// User Guide: semua pengguna yang masuk; isi disaring di server menurut izin menu (lihat UserGuideController).
Route::get('/user-guide', [\App\Http\Controllers\UserGuideController::class, 'index'])->name('user-guide.index');
Route::get('/user-guide/pdf', [\App\Http\Controllers\UserGuideController::class, 'pdf'])->name('user-guide.pdf')->middleware('throttle:20,1');
```
> Pastikan grup rute **mewajibkan login** (di EcoSystem: middleware `CheckAuthToken`). Tanpa itu halaman terbuka untuk umum. Uji dengan permintaan tanpa sesi: harus dialihkan ke halaman masuk.

### b. Sidebar (tautan di bagian bawah, dekat Settings)
```blade
<a href="{{ route('user-guide.index') }}"
   class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl {{ Request::is('user-guide*') ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
    <span class="nav-icon w-5 h-5 flex items-center justify-center"><i class="fas fa-book-open"></i></span>
    <span class="nav-text font-medium">User Guide</span>
</a>
```
Sesuaikan kelas dengan gaya sidebar website tujuan. Di EcoSystem, `app/Support/SidebarIcons.php` memetakan `fa-*` ke ikon Tabler; tambahkan baris `'fa-book-open' => 'book'` dan simbol berikut ke `partials/sidebar-icons.blade.php` bila website tujuan memakai mekanisme yang sama:
```blade
<symbol id="ti-book" viewBox="0 0 24 24"><path d="M3 19a9 9 0 0 1 9 0a9 9 0 0 1 9 0" /><path d="M3 6a9 9 0 0 1 9 0a9 9 0 0 1 9 0" /><path d="M3 6l0 13" /><path d="M12 6l0 13" /><path d="M21 6l0 13" /></symbol>
```

### c. Menu profil pojok kanan atas
```blade
<a href="{{ route('user-guide.index') }}"
   class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-gray-50 text-gray-900 text-sm transition-all font-medium">
    <i class="fas fa-book-open w-5 text-center text-gray-500"></i>
    <span>User Guide</span>
</a>
```

## 4. Titik adaptasi (satu per satu, jangan diubah diam-diam)
| Yang dibutuhkan fitur | Di EcoSystem | Bila website tujuan berbeda — ubah di |
|---|---|---|
| Daftar **slug izin** milik pengguna | `View::shared('permSlugs')` (diisi middleware `ShareMenuPermissions`, array string slug menu) | `UserGuideController::data()` → baris `$permSlugs = …` |
| **Bahasa** preferensi pengguna | `session('user_preferences.language')` (`en`/`id`) | `UserGuideController::data()` → argumen kedua `resolveLocale()` |
| Izin semu **`@approver`** | `ApprovalEngine::isApproverAnywhere()` | `UserGuideController::data()` (blok `$extra`); hapus blok + `"@approver"` di topik bila tak relevan |
| Tata letak halaman | `@extends('dashboard')` + section `title/page-title/page-subtitle/content` + stack `styles/scripts` | `resources/views/user-guide/index.blade.php` (baris awal) |
| Nama pengguna di sampul PDF | `session('user.name')` | `UserGuideController::pdf()` |
| Slug `requires` di topik | Slug menu EcoSystem | Ganti ke slug menu website tujuan di front matter topik (pemeriksa `guide-check.php` memvalidasi terhadap tabel `menu`; sesuaikan baris `DB::table('menu')->pluck('slug')` bila sumber slug berbeda) |
| Tautan sidebar/menu profil/rute | lihat bagian 3 | berkas masing-masing |

## 5. Menggabungkan dua tim tanpa bentrok
**Pisahkan "mesin" dan "isi".**

| Jenis | Berkas | Aturan |
|---|---|---|
| **Mesin** (bersama) | `app/Services/Guide/UserGuideService.php`, `app/Http/Controllers/UserGuideController.php`, `resources/views/user-guide/*`, `config/user_guide.php` (bagian `ui`, `locales`), `docs/HRGA/tools/guide-check.php`, `e2e-user-guide.mjs`, `tests/Unit/Guide/*` | Diubah hanya setelah disepakati; perubahan **disalin ke kedua website**. Catat di `00-BACA-DULU.md` (versi paket) saat ada pembaruan. |
| **Isi** (per pemilik) | `resources/guide/{en,id}/*.md` | Bebas diubah sesuai kepemilikan topik; ikuti `02-KETENTUAN-PENULISAN.md`. |
| **Bab** (bersama) | `config/user_guide.php` → `chapters` | Penambahan bab disepakati; urutan di config = urutan tampil. |

Saran alur git:
1. Satu cabang khusus (mis. `feature/user-guide`) untuk perubahan **mesin**; digabung ke kedua website secara berurutan.
2. Topik isi dikerjakan di cabang fitur masing-masing; konflik jarang terjadi karena satu topik = satu berkas.
3. Titik konflik yang wajar: `config/user_guide.php` (daftar bab) dan `04-DAFTAR-TOPIK.md`. Selesaikan dengan menggabungkan kedua daftar; pastikan key unik.
4. Sebelum menggabung ke cabang utama: jalankan `guide-check.php` dan uji unit pada hasil gabungan.
5. Berkas Blade di EcoSystem sebagian berakhir baris CRLF; jangan menormalkan massal berkas yang bukan bagian Anda (menghindari diff besar).

## 6. Hal yang sering salah (pelajaran dari EcoSystem)
- **`symfony/yaml` hanya ada di dev** → halaman error di produksi. Sudah ditangani; jangan menambahkannya kembali.
- Rute tanpa middleware login → halaman terbuka untuk umum. Selalu uji tanpa sesi.
- Memakai waktu berkas sebagai "Updated" → berubah tiap deploy. Pakai `updated` di topik.
- Menyebut slug izin di teks topik → bocorkan istilah teknis. Pemeriksa memberi peringatan.
- Pada skrip penyunting memakai `String.replace` di Node, pola `$$` di teks pengganti menjadi `$` (merusak JS `$$(...)`). Gunakan alat sunting langsung atau fungsi pengganti.
- Folder `resources/guide/` wajib ikut terunggah saat deploy; tanpa itu halaman menampilkan "belum ada topik".
- Jangan menambahkan jalur "lihat semua topik": keputusan desain — isi (halaman, pencarian, PDF) selalu sesuai izin pengguna itu sendiri, termasuk administrator.

## 7. Menguji hasil penggabungan (daftar periksa)
- [ ] `/user-guide` terbuka (Inggris dan Indonesia); tombol bahasa mengganti dan mengingat pilihan.
- [ ] Pengguna dengan akses terbatas **tidak** melihat topik di luar aksesnya (periksa juga PDF dan hasil pencarian).
- [ ] Tanpa login → dialihkan ke halaman masuk.
- [ ] Daftar isi bernomor; klik menggulir; bab aktif terbuka; Expand/Collapse all bekerja.
- [ ] Pencarian menemukan kata, klik hasil menyorot kata; `/` memfokuskan kotak cari.
- [ ] `Download PDF` (kedua bahasa) menghasilkan berkas yang bisa dibuka; sampul, daftar isi, dan kotak catatan terlihat.
- [ ] Ponsel (±360 px): tanpa gulir ke samping; daftar isi tertutup dulu dan bisa dibuka.
- [ ] Firefox dan Chrome.
- [ ] `guide-check.php` 0 galat 0 peringatan; `php artisan test tests/Unit/Guide` lulus.

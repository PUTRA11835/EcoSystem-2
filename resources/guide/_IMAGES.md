# User Guide: ketentuan gambar (tambahan untuk branch ini)

Berkas berawalan `_` tidak dimuat sebagai topik. Dokumen ini melengkapi `_AUTHORING.md` milik paket HRGA, yang melarang gambar. Branch ini mengizinkan gambar **dengan batasan berikut**; perubahan pada `guide-check.php`, `UserGuideController`, dan CSS di `user-guide/index.blade.php` dan `pdf.blade.php` perlu disepakati dengan tim HRGA sebelum digabung.

## Catatan tata letak (daftar isi di samping artikel)
Build Tailwind aplikasi (`public/build`, ikut dilacak git) bisa belum memuat kelas `lg:grid-cols-12`, `lg:col-span-*`, `lg:sticky`, `lg:overflow-y-auto`. Tanpa kelas itu daftar isi jatuh ke atas artikel. Karena itu `index.blade.php` memuat CSS grid eksplisit (`.ug-layout`, `.ug-aside`, `.ug-main`, `.ug-toc-scroll`) yang nilainya sama dengan kelas Tailwind tersebut, plus aturan `.overflow-x-hidden:has(.ug-layout)` supaya `position: sticky` bekerja (pembungkus konten di layout dashboard memakai `overflow-x-hidden`). Jangan membangun ulang `public/build` hanya untuk ini.

## Aturan
- Format penulisan: `![Teks alternatif yang menjelaskan isi gambar](images/guide/nama-berkas.png)`, lalu satu baris miring di bawahnya: `*Screenshot with sample data.*` (EN) / `*Tangkapan layar dengan data contoh.*` (ID).
- Jalur harus **relatif** (`images/guide/...`), berkas ada di `public/images/guide/`, ekstensi `png`/`jpg`/`jpeg`, maksimal 600 KB per berkas.
- Teks alternatif wajib. Kedua bahasa memakai gambar yang **sama dan berurutan** (tangkapan layar berbahasa Inggris, karena nama menu memang tetap Inggris).
- Dilarang: gambar dari alamat luar, SVG, atau gambar di luar `images/guide/`.

## Isi gambar: tanpa data sungguhan dan tanpa kredensial
- **Jangan memotret database kerja.** Gambar hanya boleh berisi data karangan (Customer A, ticket 2699xxxx, nama demo, alamat `example.com`).
- Jangan menampilkan password, token, kode 2FA, kunci API, tab Credential customer, data rekening, atau data gaji, walaupun datanya contoh.
- Gambar ditangkap tanpa bilah alamat peramban, jadi alamat server tidak ikut terlihat.

## Cara membuat ulang gambar dengan aman
1. Buat database sementara terpisah dan salin **hanya struktur tabel** dari database lokal.
2. Isi hanya tabel referensi (menu, role, akses, modul, dropdown) dan data karangan. Jangan salin tabel konfigurasi yang dapat memuat kunci.
3. Buat beberapa karyawan demo (satu per role yang ingin ditampilkan) dengan password acak yang hanya disimpan sementara.
4. Jalankan aplikasi dengan `DB_DATABASE` mengarah ke database sementara itu, masuk sebagai akun demo, lalu potret layar (Chrome/Edge headless, 1280×800).
5. Hapus database sementara dan password setelah selesai.

## Yang menjaga aturan ini
- `php docs/HRGA/tools/guide-check.php`: menolak gambar di luar `images/guide/`, tanpa teks alternatif, atau berkas yang tidak ada.
- `php artisan test tests/Unit/Guide`: gambar ada di disk, sama di kedua bahasa, punya teks alternatif, dan tersisip sebagai data URI di PDF.

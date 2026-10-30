# User Guide Editor

Berkas berawalan `_` tidak dimuat sebagai topik. Editor memungkinkan EC Administrator mengubah panduan dari **Control Center > User Guide Editor** (`/admin/user-guide`) tanpa deploy. Berkas di `resources/guide` tetap menjadi sumber bawaan.

## Cara kerjanya (lapisan timpa)
- Satu baris di tabel `user_guide_topics` = salinan penuh satu topik untuk satu bahasa.
  - key sama dengan topik berkas: **menimpa** topik itu (status *Edited*).
  - key belum ada di berkas: **topik baru** (status *Custom*).
- **Restore default** menghapus baris itu, jadi pembaca kembali ke berkas. Topik Custom ikut terhapus.
- EN dan ID disunting terpisah. Topik tanpa versi ID tetap jatuh ke versi EN seperti biasa.
- `base_hash` menyimpan md5 berkas saat topik pertama kali disunting. Bila berkas berubah setelah itu (misal lewat merge atau deploy), daftar menandai topik **default changed** supaya admin meninjau ulang.
- Tanggal `updated` terisi otomatis dengan hari penyimpanan.

## Peta kode (satu tanggung jawab per berkas)
| Berkas | Tugas |
|---|---|
| `app/Services/Guide/TopicOverrides.php` | Antarmuka: `forLocale()` dan `version()`. Satu-satunya yang diketahui mesin. |
| `app/Services/Guide/DatabaseTopicOverrides.php` | Implementasi dari tabel. Aman bila tabel belum ada. |
| `app/Services/Guide/UserGuideService.php` | Mesin bersama. Perubahan editor minimal: konstruktor opsional, `load()` = berkas + timpaan, `loadFiles()`, versi timpaan masuk tanda tangan cache. |
| `app/Services/Guide/GuideContentRules.php` | Aturan isi (murni, tanpa DB). |
| `app/Services/Guide/GuideTopicEditor.php` | Daftar status, baca, simpan, restore, pratinjau. |
| `app/Http/Requests/SaveGuideTopicRequest.php` | Validasi bentuk data, lalu memanggil `GuideContentRules`. |
| `app/Http/Controllers/UserGuideAdminController.php` | Hanya HTTP. |
| `app/Providers/GuideServiceProvider.php` | Mengikat `TopicOverrides` ke implementasi database. |
| `app/Models/UserGuideTopic.php` | Model, tercatat di Audit Log (modul "User Guide", isi artikel tidak dicatat). |
| `resources/views/admin/user-guide/` | `index` (daftar) dan `form` (sunting, pratinjau). |

## Aturan isi yang ditegakkan editor
Sama dengan `guide-check.php`: tanpa em dash atau en dash di teks biasa (tulisan persis di layar dalam `kode` boleh), tanpa HTML mentah, tanpa tautan keluar, gambar hanya `images/guide/*.png|jpg` yang ada dan ber-alt, `[[key]]` harus menuju topik yang ada, `requires` harus slug menu, `@approver`, atau `@role:Nama Role` dengan nama role yang ada.
Gambar tidak diunggah lewat editor; tambahkan sebagai berkas di `public/images/guide` lewat repo.

## Akses
Menu `control-center.user-guide`, baru aktif untuk EC Administrator saja (aturan `MenuRegistrar`). Role lain diberi lewat Control Center > Menu Access.
Isi yang dibaca pengguna tetap disaring di server menurut `requires` (termasuk `@role:`); editor tidak membuka jalur "lihat semua".

## Menambah fitur editor
- Aturan isi baru: ubah `GuideContentRules` dan tambah kasus di `GuideContentRulesTest`.
- Perilaku simpan atau baca: ubah `GuideTopicEditor` dan `GuideTopicEditorTest`.
- Jangan menambah logika ke controller atau view.

## Pengujian
`php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit tests/Unit/Guide` (sqlite memori; dilewati bila driver tidak ada), `php docs/HRGA/tools/guide-check.php`, `docs/HRGA/tools/e2e-user-guide-editor.mjs` (Chrome).

## Penyunting visual (28 Okt malam)
Tab **Visual** (bawaan), **Markdown**, **Preview** pada formulir sunting. Penyimpanan tetap Markdown; tab Visual hanya cara menyunting.
- `public/js/guide-editor.js`: penyunting berbasis `contenteditable` tanpa library luar. Toolbar: gaya (paragraf/judul/sub-judul), tebal/miring/coret, **Screen name** (`kode`), daftar bertingkat, kotak catatan, tabel (+baris/kolom), tautan topik, hapus format, undo/redo sendiri (operasi kotak catatan/tabel tidak masuk riwayat bawaan peramban). Tempelan dari Word/Google Docs dibersihkan (hanya struktur yang dikenal yang dipertahankan). Ctrl+S menyimpan; peramban memperingatkan perubahan yang belum disimpan.
- Konversi HTML ke Markdown ditulis sendiri (`GuideEditor.toMarkdown`) untuk himpunan bagian yang dipakai panduan. Isi awal dirender server dengan mesin yang sama seperti halaman baca (`GuideTopicEditor::preview`), jadi tampilan sunting = tampilan baca. Gaya dipakai bersama lewat partial `resources/views/user-guide/_body-css.blade.php`.
- Teks hanya ditulis ulang ke Markdown bila Anda benar-benar menyunting di tab Visual; membuka lalu menyimpan tanpa perubahan tidak mengubah isi. Komentar HTML (`<!-- sources ... -->`) dipertahankan dari tab Markdown.
- Chrome menaruh daftar di dalam `<p>`; penyunting memecahnya (`fixNesting`) dan konverter juga menangani bentuk itu.
- Uji: `docs/HRGA/tools/e2e-user-guide-visual.mjs` (bolak-balik 136 topik, kasus tepi, interaksi, simpan/hapus topik uji, responsif).
- Rencana unggah gambar: `docs/HRGA/11-RENCANA-UPLOAD-GAMBAR-GUIDE.md`.

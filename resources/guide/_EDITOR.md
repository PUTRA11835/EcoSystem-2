# User Guide Editor (tambahan untuk branch ini)

Berkas berawalan `_` tidak dimuat sebagai topik. Editor memungkinkan EC Administrator mengubah panduan dari **Control Center > User Guide Editor** tanpa deploy. Berkas di `resources/guide` tetap menjadi sumber bawaan.

## Cara kerjanya (lapisan timpa)
- Satu baris di tabel `user_guide_topics` = salinan penuh satu topik untuk satu bahasa.
  - key sama dengan topik berkas: **menimpa** topik itu (status *Edited*).
  - key belum ada di berkas: **topik baru** (status *Custom*).
- **Restore default** menghapus baris itu, jadi pembaca kembali ke berkas. Topik Custom ikut terhapus.
- EN dan ID disunting terpisah. Topik tanpa versi ID tetap jatuh ke versi EN seperti biasa.
- `base_hash` menyimpan md5 berkas saat topik pertama kali disunting. Bila berkas berubah setelah itu (misal lewat merge dari tim lain), daftar menandai topik **default changed** supaya admin meninjau ulang.

## Peta kode (satu tanggung jawab per berkas)
| Berkas | Tugas |
|---|---|
| `app/Services/Guide/TopicOverrides.php` | Antarmuka: `forLocale()` dan `version()`. Satu-satunya yang diketahui mesin. |
| `app/Services/Guide/DatabaseTopicOverrides.php` | Implementasi dari tabel. Aman bila tabel belum ada. |
| `app/Services/Guide/UserGuideService.php` | Mesin bersama. Perubahan minimal: konstruktor opsional, `load()` = berkas + timpaan, `loadFiles()`, versi timpaan masuk tanda tangan cache. |
| `app/Services/Guide/GuideContentRules.php` | Aturan isi (murni, tanpa DB). |
| `app/Services/Guide/GuideTopicEditor.php` | Daftar status, baca, simpan, restore, pratinjau. |
| `app/Http/Requests/SaveGuideTopicRequest.php` | Validasi bentuk data, lalu memanggil `GuideContentRules`. |
| `app/Http/Controllers/UserGuideAdminController.php` | Hanya HTTP. |
| `app/Providers/GuideServiceProvider.php` | Mengikat `TopicOverrides` ke implementasi database. |
| `app/Models/UserGuideTopic.php` | Model, tercatat di Audit Log (modul "User Guide", isi artikel tidak dicatat). |
| `resources/views/admin/user-guide/` | `index` (daftar) dan `form` (sunting, pratinjau). |

## Aturan isi yang ditegakkan editor
Sama dengan `guide-check.php`: tanpa em dash atau en dash, tanpa HTML mentah, tanpa tautan keluar, gambar hanya `images/guide/*.png|jpg` yang ada dan ber-alt, `[[key]]` harus menuju topik yang ada, `requires` harus slug menu, `@approver`, atau `@role:` dengan nama role yang ada.
Gambar tidak diunggah lewat editor; tambahkan sebagai berkas di `public/images/guide` lewat repo.

## Akses
Menu `control-center.user-guide`, baru aktif untuk EC Administrator saja (aturan `MenuRegistrar`). Role lain diberi lewat Control Center > Menu Access.

## Menambah fitur editor
- Aturan isi baru: ubah `GuideContentRules` dan tambah kasus di `GuideContentRulesTest`.
- Perilaku simpan atau baca: ubah `GuideTopicEditor` dan `GuideTopicEditorTest`.
- Jangan menambah logika ke controller atau view.

## Pengujian
`php artisan test tests/Unit/Guide` (sqlite memori). Tampilan diverifikasi langsung di peramban memakai database demo sementara, lihat `_IMAGES.md`.

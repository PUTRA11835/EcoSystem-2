# 01 — Penjelasan fitur User Guide

## Ringkasan
Halaman `/user-guide` yang menampilkan panduan pengguna **bertahap dan berurutan**, dalam **Bahasa Inggris dan Bahasa Indonesia**, dengan daftar isi bernomor, pencarian, dan unduhan PDF. Isi yang tampil **menyesuaikan akses menu** pengguna.

## Perilaku (yang harus tetap sama di semua website)

### 1. Tempat menu
Tautan **User Guide** ada di dua tempat: sidebar (bagian bawah, dekat Settings; terlihat semua pengguna yang masuk) dan menu profil di pojok kanan atas. Halaman dapat dibuka semua pengguna yang sudah masuk.

### 2. Bahasa
Urutan penentuan: `?lang=` di alamat → preferensi pengguna (`Settings › language`) → Inggris (bawaan).
Di bagian atas halaman ada tombol `English | Indonesia`. Tombol ini **hanya mengubah bahasa panduan** (diingat di browser lewat `localStorage`), **tidak** mengubah pengaturan akun — karena terjemahan antarmuka aplikasi belum menyeluruh.
Topik yang belum ada terjemahannya tetap tampil (bahasa Inggris) dengan catatan "belum tersedia dalam bahasa Anda".

### 3. Penyaringan akses (di server)
- Tiap topik punya `requires` (daftar izin menu). Topik tampil bila pengguna memiliki **salah satu**; `requires` kosong = semua pengguna.
- Topik yang tidak boleh dilihat **tidak dikirim sama sekali** (bukan hanya disembunyikan dengan CSS) — juga tidak masuk PDF dan indeks pencarian.
- Bab yang tidak punya topik terlihat tidak ditampilkan; penomoran dihitung dari yang tampil (tidak bolong).
- Izin semu `@approver`: pengguna yang ditunjuk sebagai penyetuju di alur persetujuan melihat topik My Approvals walau tanpa izin menu (menyamai sidebar).
- **Tidak ada mode "lihat semua"** (sengaja): tampilan, pencarian, dan PDF selalu sesuai izin menu pengguna itu sendiri — termasuk administrator. Siapa pun yang tidak punya hak akses suatu topik tidak dapat melihat atau mengunduhnya. Untuk meninjau seluruh isi, baca berkas `resources/guide/**` atau jalankan `guide-check.php`.

### 4. Daftar isi ("Table of Contents" / "Daftar Isi")
- Bab = kartu bernomor (1, 2, …) dengan jumlah topik; topik bernomor (1.1, 1.2, …).
- Nomor yang sama muncul di judul isi dan di PDF.
- Klik menggulir halus ke topik dan menyorotnya; topik yang sedang dibaca tersorot (scroll-spy) dan **sub-judulnya tampil di bawahnya**.
- Awalnya hanya bab yang sedang dibaca yang terbuka; bab lain terbuka otomatis saat dibaca; tombol `Expand all` / `Collapse all`.
- Tautan langsung ke topik: `/user-guide#topic-<key>` (berguna untuk ikon "?" kontekstual di halaman lain kelak).
- Di layar kecil daftar isi tertutup dulu dan bisa dibuka.

### 5. Pencarian
- Kotak cari di atas daftar isi; teks penuh (judul, sub-judul, isi) pada topik yang boleh dilihat pengguna.
- Semua kata harus cocok (AND); hasil diurutkan: judul > sub-judul > isi; maksimal 40 hasil ditampilkan; abaikan huruf besar/kecil dan aksen.
- Klik hasil → menggulir ke bagian itu dan **menyorot kata yang dicari**. Tombol `/` memindahkan fokus ke kotak cari; `Esc` membersihkan; panah atas/bawah memilih hasil.
- Keadaan "tidak ditemukan" ditampilkan.

### 6. PDF
Tombol `Download PDF` menghasilkan PDF **sesuai bahasa dan hak akses** pengguna: sampul (judul, bahasa, tanggal, nama pengguna, jumlah topik), daftar isi bernomor, bab dan topik bernomor, kotak catatan berwarna, nama menu/tombol bergaya kotak tebal, tabel, nomor halaman. Dibatasi 20 unduhan/menit per pengguna. Memakai `barryvdh/laravel-dompdf`.

### 7. Tanggal "Updated"
Tanggal di bagian atas = `updated` **terbaru** dari topik yang tampil (bukan waktu berkas, karena waktu berkas berubah saat deploy/ekstrak zip). Tiap topik juga menampilkan "Topic updated" di bawahnya. Aturan: ubah `updated` setiap kali isi topik berubah (di kedua bahasa).

### 8. Isi panduan
- Satu berkas Markdown = satu topik, per bahasa: `resources/guide/{en,id}/NNN-key.md`.
- Pola isi seragam: tujuan singkat → siapa yang menggunakan → sebelum mulai → langkah demi langkah → apa yang terjadi selanjutnya → pertanyaan umum → topik terkait.
- Kotak catatan `> [!NOTE|TIP|IMPORTANT|WARNING]`, tautan antartopik `[[key]]`, nama menu/tombol dalam `` `kode` ``.

## Mengapa dirancang begini (alasan keputusan)
| Keputusan | Alasan |
|---|---|
| Isi berupa berkas Markdown di repo | Dapat ditinjau lewat git, ikut deploy, tidak butuh database/migrasi (cocok untuk hosting tanpa terminal). |
| Penyaringan di server | Keamanan: pengguna tidak boleh menerima isi menu yang bukan haknya; PDF dan pencarian otomatis konsisten. |
| Tanggal dari `updated`, bukan waktu berkas | Waktu berkas berubah saat zip diekstrak; `updated` stabil dan dikendalikan penulis. |
| Tombol bahasa terpisah dari Settings | Terjemahan aplikasi belum menyeluruh; panduan dua bahasa tetap bisa dipakai. |
| Nama menu tetap bahasa Inggris di panduan Indonesia | Itu yang tampil di layar; pengguna mencocokkan teks panduan dengan layar. |
| Tanpa `symfony/yaml` | Hanya dependensi pengembangan; di produksi tidak terpasang. Front matter memakai pembaca sendiri. |
| Satu topik = satu berkas, nama sama di kedua bahasa | Mudah dibandingkan, mudah diperiksa otomatis, minim konflik saat penggabungan. |
| Tanpa gambar/tangkapan layar (untuk saat ini) | Cepat usang dan mahal dirawat; teks dulu. |

## Batasan dan pengembangan berikutnya (belum dikerjakan)
- Editor di website (Markdown + pratinjau, riwayat perubahan) untuk yang berwenang — Tahap 3.
- Ikon "?" kontekstual di halaman lain yang membuka topik terkait.
- "Apakah topik ini membantu?" dan nomor halaman pada daftar isi PDF.
- Beberapa fitur hanya dijelaskan garis besarnya (lihat catatan penulis di `04-DAFTAR-TOPIK.md` dan log sesi proyek).

## Uji yang sudah ada
- `tests/Unit/Guide/UserGuideServiceTest.php` — bahasa, penyaringan, render Markdown, penomoran, pembaca front matter.
- `docs/HRGA/tools/guide-check.php` — pemeriksa isi (pasangan bahasa, izin, tautan, HTML terlarang, tanda sumber, dst.).
- `docs/HRGA/tools/e2e-user-guide.mjs` — uji browser (Chrome): daftar isi, pencarian, bahasa, penomoran, akordeon, ponsel/tablet/desktop.

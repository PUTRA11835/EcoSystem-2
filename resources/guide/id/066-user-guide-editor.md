---
key: user-guide-editor
chapter: administration
order: 66
title: User Guide Editor
summary: Ubah teks panduan ini, tambah topik sendiri, tentukan siapa yang melihat tiap topik, dan kembalikan ke versi awal.
requires: [control-center.user-guide]
updated: 2026-10-28
---
`User Guide Editor` memungkinkan administrator mengubah apa yang dibaca Employee di User Guide tanpa menunggu rilis baru. Topik ini menjelaskan cara mengubah topik, menambah topik baru, menentukan siapa yang dapat melihatnya, dan kembali ke teks awal.

## Siapa yang menggunakan

Administrator yang merawat panduan. Secara bawaan hanya administrator tertinggi yang memiliki `User Guide Editor`; role lain mendapatkannya lewat `Menu Access`.

## Sebelum mulai

- Buka `Control Center` › `User Guide Editor`. Pilih dulu `English` atau `Bahasa Indonesia`: tiap bahasa disunting terpisah.
- Setiap topik memiliki status:
  - `Default`: teks awal yang datang bersama aplikasi.
  - `Edited`: Anda mengubah topik awal. Employee membaca versi Anda.
  - `Custom`: topik yang Anda buat sendiri.
- Topik bertanda `default changed` pernah Anda sunting, lalu teks awalnya diperbarui. Buka topik itu dan periksa apakah versi Anda masih benar.

> [!IMPORTANT]
> Employee membaca perubahan Anda seketika. Jangan menaruh kata sandi, nama asli, gaji, atau data pribadi lain di panduan.

## Langkah demi langkah

### Mengubah topik

1. Di daftar, klik judul topik atau `Edit`.
2. Ubah `Title` dan `Summary`, lalu sunting teks di tab `Visual`. Tampilannya sama dengan panduan itu sendiri, jadi yang Anda lihat adalah yang dibaca Employee. Gunakan toolbar di atas teks:
   - Daftar gaya mengatur `Paragraph`, `Heading` (bagian), atau `Subheading`.
   - `B`, `I`, dan coret memformat kata. Pilih teksnya terlebih dahulu.
   - `Screen name` menandai nama menu, tombol, atau kolom persis seperti di layar. Tampil dalam kotak abu-abu.
   - Tombol daftar membuat daftar berpoin atau bernomor. Tekan `Tab` di dalam daftar untuk menjorokkan butir dan `Shift` + `Tab` untuk mengembalikannya. Tekan `Enter` pada butir kosong untuk keluar dari daftar.
   - `Call-out` mengubah paragraf tempat kursor berada menjadi kotak `Note`, `Tip`, `Important`, atau `Warning`. Tekan `Enter` dua kali pada baris kosong terakhir untuk keluar dari kotak.
   - `Table` menyisipkan tabel. Saat kursor berada di tabel, muncul bilah untuk menambah atau menghapus baris dan kolom. Tekan `Tab` untuk pindah ke sel berikutnya.
   - `Topic link` menautkan kata yang dipilih (atau menyisipkan judulnya) ke topik lain.
   - Undo dan redo adalah tombol panah melengkung, atau `Ctrl` + `Z` dan `Ctrl` + `Y`.
3. Gunakan tab di atas teks bila perlu: `Markdown` menampilkan teks yang sama dalam bentuk polos untuk penyuntingan lanjutan, dan `Preview` menampilkan persis bagaimana panduan menampilkan topik itu.
4. Klik `Save` (atau tekan `Ctrl` + `S`). Halaman yang tampil memuat teks baru, dan `View` membuka panduan pada topik itu. Bila Anda meninggalkan halaman dengan perubahan yang belum disimpan, peramban meminta konfirmasi.

### Menentukan siapa yang melihat topik

1. Di `Visible to`, centang `Menu access`, nama `Role`, atau `Designated approvers` yang boleh melihat topik. Seorang Employee cukup memiliki salah satu pilihan yang dicentang.
2. Biarkan semuanya tidak dicentang agar topik tampil untuk semua Employee.

### Menempatkan topik

1. Di `Placement`, pilih `Chapter`.
2. Atur `Order in chapter`. Angka lebih kecil tampil lebih dulu.

### Menambah topik baru

1. Klik `New topic`.
2. Isi `Key` (huruf kecil, angka, dan tanda hubung; tidak dapat diubah kemudian), `Title`, `Summary`, dan `Content (Markdown)`.
3. Pilih bab dan siapa yang dapat melihatnya, lalu `Save`.

### Menautkan topik dan menambah gambar

- Untuk menaut ke topik lain, tulis kuncinya di antara dua tanda kurung siku pembuka dan dua tanda kurung siku penutup. Kotak `Writing tips` di editor menunjukkan bentuk persisnya.
- Gambar adalah berkas yang ditempatkan pengembang di folder gambar panduan aplikasi, jadi gambar tidak dapat diunggah lewat editor. Setelah berkasnya ada, kotak `Writing tips` menunjukkan cara memasangnya di topik. Setiap gambar perlu deskripsi singkat untuk pembaca layar.

### Mengembalikan ke versi awal

1. Buka topik berstatus `Edited`, klik `Restore default`, lalu konfirmasi.
2. Employee kembali membaca teks awal. Pada topik `Custom`, tombol yang sama bernama `Delete topic` dan menghapus topik itu.

## Apa yang terjadi selanjutnya

Employee melihat perubahan seketika, di panduan, di pencarian, dan di PDF yang diunduh. Setiap penyimpanan tercatat di `Audit Log` pada modul `User Guide` (teks artikelnya sendiri tidak dicatat). Tanggal pada `Updated` menjadi hari Anda menyimpan.

## Pertanyaan umum

**Editor menolak menyimpan.** Baca pesan merah di bawah kolom. Penyebab yang umum: tanda pisah panjang (pakai koma atau titik dua), alamat web, HTML mentah, berkas gambar yang tidak ada, tautan ke topik yang tidak ada, atau `Key` yang sudah dipakai.

**Topik ada dalam bahasa Inggris tetapi belum dalam bahasa Indonesia.** Employee yang membaca bahasa Indonesia melihat teks Inggris dengan catatan. Buka topik itu di `Bahasa Indonesia` dan simpan terjemahannya.

**Saya tidak melihat `User Guide Editor` di menu.** Fitur ini memerlukan akses tersendiri. Hubungi administrator Anda.

## Topik terkait

- [[control-center]]
- [[roles-and-menu-access]]
- [[welcome]]

<!-- sources: routes/web.php, app/Http/Controllers/UserGuideAdminController.php, resources/views/admin/user-guide/index.blade.php, resources/views/admin/user-guide/form.blade.php, app/Services/Guide/GuideContentRules.php -->

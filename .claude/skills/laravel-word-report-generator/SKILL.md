---
name: laravel-word-report-generator
description: >
  Membuat laporan otomatis dengan cara membaca struktur sebuah file Word (.docx)
  yang diupload user sebagai contoh/template, mengambil semua data terkait dari
  database lewat function/tool calling yang sudah tersedia di sistem Laravel,
  lalu menyusun laporan baru dengan format/layout SAMA PERSIS seperti template
  asli (font, margin, gaya tabel, kop, urutan bagian tidak boleh berubah),
  hanya isinya yang diganti data nyata. WAJIB gunakan skill ini setiap kali
  user mengupload file .docx dan menyebut kata "laporan", "report", "template",
  "contoh format", "buatkan laporan seperti ini", "sama persis dengan
  template". Skill ini juga wajib dipakai bila user meminta output laporan
  dalam bentuk .docx dan/atau .pdf hasil generate dari data sistem, bukan
  sekadar meringkas isi dokumen yang diupload.
---

# Laravel Word Report Generator

Skill ini mengatur bagaimana AI (yang terhubung ke database via function/tool
calling di backend Laravel) mengubah sebuah **file Word contoh/template**
menjadi **laporan baru yang terisi data nyata**, dengan format/layout **SAMA
PERSIS** seperti template asli — bukan sekadar "mirip". Pendekatannya: pahami
dulu bentuk & topik template secara keseluruhan, tarik semua data yang
relevan dari database, lalu isi ke dalam struktur template tanpa mengubah
satu pun elemen visual (font, warna, margin, border tabel, urutan bagian,
kop/logo/footer). Yang berubah HANYA nilai datanya; yang berulang seperti
baris tabel di-duplikasi memakai format baris yang identik dengan aslinya.

Output akhir: satu file `.docx` (format 100% identik dengan template) dan
satu file `.pdf` hasil konversi darinya.

---

## Alur Kerja (Wajib Diikuti Berurutan)

### Tahap 1 — Baca & Petakan Struktur Dokumen

1. Ekstrak seluruh struktur dokumen Word yang diupload: heading & levelnya,
   paragraf, isi tabel (header kolom + baris), teks berlabel (misal
   "Nama:", "Tanggal:", "Total:"), serta elemen berulang (baris tabel yang
   jelas berupa daftar/list item).
2. Catat juga elemen non-data yang harus dipertahankan apa adanya: judul
   dokumen/kop surat, logo/gambar, nomor halaman, footer, watermark,
   disclaimer, tanda tangan.
3. Jangan langsung mulai mengisi data. Hasil Tahap 1 harus berupa **peta
   struktur** (outline) sebelum lanjut ke Tahap 2. Contoh format internal
   peta struktur:

   ```
   1. Heading "LAPORAN PENJUALAN BULANAN" — statis, jangan diubah
   2. Paragraf "Periode: ___" — butuh data (rentang tanggal)
   3. Tabel "Data Transaksi" (kolom: No, Tanggal, Produk, Qty, Total)
      — berulang, 1 baris = 1 record dari DB
   4. Paragraf "Total Pendapatan: ___" — butuh data (agregat/SUM)
   5. Bagian "Catatan" — statis/opsional, boleh dikosongkan bila tak ada data
   ```

### Tahap 2 — Kenali Topik Laporan, Lalu Tarik Semua Data Terkait

1. Dari peta struktur Tahap 1, kenali **topik/jenis laporan** secara umum
   (misal: laporan penjualan bulanan, laporan absensi pegawai, laporan
   inventaris) berdasarkan judul dokumen, heading, dan judul kolom tabel
   secara keseluruhan — cukup untuk tahu "laporan ini tentang apa", tidak
   perlu memetakan tiap label/kolom satu per satu.
2. Cocokkan topik itu dengan function/tool yang tersedia di sistem (lihat
   **Kontrak Function Calling** di bawah), lalu tarik **semua data yang
   relevan** untuk periode/konteks yang diminta user atau yang tersirat di
   dokumen. Ambil selengkap mungkin — termasuk data yang levelnya lebih
   detail dari contoh di template — karena jumlah baris/isi laporan akhir
   akan menyesuaikan data yang benar-benar ada, bukan meniru persis jumlah
   baris contoh di template.
3. Jika satu tabel di dokumen mewakili daftar/list (transaksi, pegawai,
   dsb), pastikan function calling mengembalikan seluruh baris yang
   relevan, bukan hanya satu record — simpan sebagai array/list untuk
   dipakai mengisi tabel di Tahap 3.
4. Jika ada data agregat (total, rata-rata, jumlah), utamakan function
   agregasi resmi bila tersedia (misal `getMonthlySalesSummary`) agar
   konsisten dengan laporan lain di sistem — jangan menghitung manual bila
   function-nya sudah ada.
5. Jika topik atau periode tidak jelas dari dokumen maupun permintaan user,
   tanyakan singkat ke user (maks. 1 pertanyaan, dengan opsi jelas) sebelum
   menarik data — jangan menebak periode secara sepihak.
6. Validasi hasil: jika suatu bagian dari template ternyata tidak ada
   padanan datanya di database, catat sebagai "data tidak ditemukan" —
   jangan mengarang angka atau isi teks.

### Tahap 3 — Copy & Edit In-Place: SAMA PERSIS dengan Template (Bukan Generate dari Nol)

Tujuannya: dokumen baru **identik secara visual** dengan template asli —
font, ukuran, warna, margin, border tabel, spasi, urutan bagian, kop, logo,
footer, semuanya tidak boleh berubah sedikit pun. Yang berubah **hanya
nilai/isi datanya**, diambil dari data nyata di Tahap 2.

**Jangan pernah membuat dokumen baru dari nol yang "mirip" template** (baik
lewat penulisan XML manual maupun lewat library level-tinggi seperti
python-docx/phpword yang membangun ulang objek dokumen — keduanya berisiko
diam-diam mereformat elemen yang seharusnya tidak disentuh). Cara yang wajib
dipakai: **copy file template asli, lalu edit langsung di dalam struktur
internalnya**, sehingga elemen non-teks tidak pernah dibongkar ulang sama
sekali — hanya nilai teksnya yang berubah.

Langkah wajib:

1. Copy file template asli, lalu `unzip template.docx -d unpacked/` untuk
   mengakses struktur internalnya (`unpacked/word/document.xml`).
2. Gabungkan run teks yang bersebelahan dengan format sama (tulis skrip
   kecil — mis. lewat python-docx/lxml — untuk merge run yang terpecah
   akibat marker spell-check dll), supaya label/placeholder yang terpecah
   di XML bisa dicari sebagai string utuh.
3. Ganti **HANYA teks di dalam run yang sudah ada** di `document.xml`
   dengan data nyata dari Tahap 2 — jangan reformat, pretty-print, atau
   menulis ulang XML di luar teks yang diganti.
4. Untuk baris tabel yang jumlahnya perlu menyesuaikan data (mis. daftar
   transaksi): **duplikasikan node `<w:tr>` yang sudah ada apa adanya**,
   lalu ganti isi selnya saja — border/font/alignment ikut ter-copy persis
   tanpa perlu didefinisikan ulang. Jangan membuat definisi
   style/border baru.
5. Jangan sentuh sama sekali elemen non-teks: gambar/logo di
   `word/media/`, kop, footer, margin, page setup, style tabel — semua ini
   harus tetap 100% seperti template asli karena memang tidak dibongkar
   ulang. Bila suatu bagian pada template ternyata tidak punya padanan
   data di database, tandai dengan jelas (misal cetak miring "Data tidak
   tersedia") memakai style teks yang sudah ada di template — jangan
   menambah style baru — daripada dibiarkan kosong tanpa keterangan atau
   diisi asal.
6. `zip` ulang menjadi file `.docx` baru — jangan menimpa file template
   asli yang diupload user.
7. Render hasilnya ke PDF (`soffice --headless --convert-to pdf`, atau
   apa pun yang tersedia di sandbox) dan periksa visualnya untuk
   memastikan tidak ada yang bergeser, sebelum dianggap final. Ini
   sekaligus jadi draf untuk output PDF di Tahap 4.

> Prompt operasional ringkas (acuan langkah 1–7 di atas):
>
> ```
> Gunakan file template .docx ini sebagai BASIS LANGSUNG, bukan acuan untuk
> membuat dokumen baru dari nol.
>
> 1. Copy file template asli, lalu unzip untuk mengakses struktur internalnya
>    (word/document.xml).
> 2. Gabungkan run teks yang terpecah (karena spell-check marker dll) agar
>    label/placeholder bisa ditemukan sebagai string utuh.
> 3. Ganti HANYA teks di dalam run yang sudah ada dengan data nyata — jangan
>    reformat, pretty-print, atau menulis ulang XML di luar teks yang diganti.
> 4. Untuk baris tabel yang jumlahnya menyesuaikan data: duplikasikan node
>    <w:tr> yang sudah ada apa adanya, lalu ganti isi selnya saja. Jangan
>    membuat definisi style/border baru.
> 5. Jangan sentuh sama sekali elemen non-teks: gambar, logo, kop, footer,
>    margin, page setup, style tabel — semua ini harus tetap 100% seperti
>    template asli karena memang tidak dibongkar ulang.
> 6. Zip ulang menjadi file .docx baru (jangan menimpa file template asli).
> 7. Render hasilnya ke PDF dan periksa visualnya untuk memastikan tidak ada
>    yang bergeser sebelum dianggap final.
> ```

### Tahap 4 — Konversi ke `.pdf`

Konversi hasil `.docx` dari Tahap 3 menjadi `.pdf` dengan tetap menjaga
layout (jangan re-generate dari teks mentah, karena berisiko merusak
tabel/formatting). Sertakan kedua file (`.docx` dan `.pdf`) sebagai output
akhir ke user.

### Tahap 5 — Ringkasan ke User

Setelah kedua file jadi, berikan ringkasan singkat dalam **bahasa manusia
biasa/awam** — user yang membaca BUKAN programmer, jadi ringkasan ini
JANGAN pernah menyebut istilah teknis apa pun: nama tabel database, nama
function/tool, nama kolom mentah, query, ataupun kode. Sebutkan datanya
secara natural (mis. "data tiket dan data pelanggan periode Juli 2026",
bukan "tabel `ticket` dan `customer`"; "hasil rekap status tiket", bukan
"hasil agregasi dari function `aggregate_data`").

Cakup: apa isi laporannya (topik data, secara natural), periode/filter yang
dipakai, jumlah baris/data yang berhasil terisi, dan bagian mana (jika ada)
yang datanya tidak ditemukan — semua dengan kalimat sehari-hari. Ini penting
agar user bisa memverifikasi kebenaran laporan sebelum dipakai resmi, tanpa
perlu paham struktur database di baliknya.

---

## Kontrak Function/Tool Calling (Isi Sesuai Sistem Laravel Anda)

Bagian ini WAJIB disesuaikan dengan function calling yang sudah ada di
integrasi AI-Laravel Anda. Contoh skema (sesuaikan nama & parameter):

```json
[
  {
    "name": "get_sales_transactions",
    "description": "Ambil daftar transaksi penjualan dalam rentang tanggal tertentu, opsional filter produk/kategori/status.",
    "parameters": {
      "start_date": "YYYY-MM-DD",
      "end_date": "YYYY-MM-DD",
      "product_id": "optional",
      "status": "optional"
    }
  },
  {
    "name": "get_employee_by_department",
    "description": "Ambil daftar pegawai berdasarkan departemen/divisi.",
    "parameters": { "department_id": "optional" }
  },
  {
    "name": "get_report_summary",
    "description": "Ambil data agregat (total, rata-rata, count) untuk periode tertentu agar konsisten dengan sistem.",
    "parameters": { "metric": "string", "start_date": "YYYY-MM-DD", "end_date": "YYYY-MM-DD" }
  }
]
```

**Aturan penting:**
- AI tidak boleh menghitung ulang angka finansial/agregat secara manual bila
  sistem sudah punya function resmi untuk itu — selalu utamakan hasil dari
  function agregasi resmi agar tidak beda dengan laporan lain di sistem.
- Bila function yang dibutuhkan belum ada (misal template minta data yang
  tidak ter-cover function apa pun), laporkan ke user bagian mana yang
  tidak bisa diisi otomatis, daripada memaksakan mengarang data.

---

## Catatan Implementasi Teknis (Sisi Laravel)

- **Baca struktur .docx**: gunakan library yang bisa membaca XML internal
  docx (isi `document.xml`, termasuk tabel `<w:tbl>`, heading style, dsb) —
  di PHP bisa lewat `phpoffice/phpword`, atau kirim file ke layanan/skill
  yang memakai `python-docx` untuk ekstraksi terstruktur sebelum dikirim
  sebagai konteks ke AI.
- **Kirim struktur ke AI, bukan hanya teks polos**: sertakan info heading
  level, batas tabel, dan header kolom secara eksplisit dalam payload ke
  AI (bukan hasil `strip_tags` biasa), supaya inferensi di Tahap 2 akurat.
- **Isi ulang docx**: JANGAN pakai `phpoffice/phpword`/`python-docx` untuk
  membangun ulang objek dokumen (API level-tinggi begini rawan diam-diam
  mereformat elemen) — ikuti pendekatan copy-&-edit-in-place di Tahap 3
  (unzip, ganti teks run yang sudah ada di `document.xml`, duplikasi node
  `<w:tr>` apa adanya, zip ulang). Library docx boleh dipakai sebatas alat
  bantu baca-saja (mis. cari batas run) atau untuk merge run yang terpecah,
  bukan untuk menulis ulang dokumen. Convert ke PDF via
  `libreoffice --headless --convert-to pdf` atau layanan konversi yang
  sudah dipakai di sistem.
- **Batasi ukuran/kompleksitas**: untuk tabel dengan ratusan/ribuan baris,
  pertimbangkan generate di background job (queue Laravel) agar tidak
  timeout di request HTTP, lalu beri notifikasi ke user saat selesai.
- **Keamanan data**: pastikan function calling yang diekspos ke AI sudah
  dibatasi scope-nya (misal user hanya bisa akses data tenant/perusahaannya
  sendiri) — jangan biarkan AI memanggil function dengan parameter di luar
  hak akses user yang sedang login.

---

## Batasan & Hal yang Harus Dihindari

- Jangan membuat dokumen baru dari nol yang "mirip" template — selalu
  gunakan file template asli sebagai basis agar formatting 100% konsisten.
- Jangan membangun ulang dokumen lewat library level-tinggi (python-docx/
  phpword dipakai untuk MENULIS dokumen) ataupun menulis ulang/pretty-print
  XML `document.xml` di luar teks yang memang diganti — edit run teks dan
  duplikasi node `<w:tr>` yang sudah ada apa adanya (lihat Tahap 3).
- Jangan mengarang angka, nama, atau tanggal apa pun yang tidak berasal
  dari hasil function calling.
- Jangan mengasumsikan periode laporan (bulan/tahun) tanpa dasar dari
  permintaan user atau isi dokumen — jika tidak jelas, tanyakan.
- Jangan mengubah elemen statis (kop surat, logo, nomor dokumen resmi,
  tanda tangan) yang seharusnya tetap sama di semua laporan.
- Jangan melewati Tahap 1 (pemetaan struktur) — mengisi data langsung
  tanpa peta struktur eksplisit meningkatkan risiko salah menempatkan data
  di kolom/bagian yang salah.

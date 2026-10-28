---
key: my-profile
chapter: my-workspace
order: 10
title: My Profile
summary: Periksa dan lengkapi data pribadi Anda, pantau kelengkapan data, dan pahami saat HR mengunci profil Anda.
requires: [my-profile.section.basic_data, my-profile.section.address, my-profile.section.identification, my-profile.section.bank, my-profile.section.attachment]
updated: 2026-10-27
---
`My Profile` berisi data pribadi Anda: identitas, alamat, rekening bank, dokumen, dan lainnya. Data yang lengkap membantu HR memproses Contract, Payroll, dan BPJS Anda dengan benar.

## Siapa yang menggunakan

Setiap Employee. Tab yang tampil bergantung pada akses Anda, dan Consultant External melihat tab yang lebih sedikit.

## Sebelum Anda mulai

Siapkan KTP, nomor pajak, data rekening bank, dan dokumen pendukung. Kolom bertanda bintang merah wajib diisi.

## Langkah demi langkah

### Membuka dan membaca profil

1. Pilih `My Profile` di sidebar, atau buka dari menu di bawah nama Anda.
2. Baca kartu header yang berisi foto atau inisial dan data utama Anda.
3. Pilih tab seperti `Basic Data`, `Address`, `Identification`, `Family`, `Education`, `Qualification`, `Contract`, `Bank Account`, `Basic Payment`, atau `Attachment`.

### Melengkapi data yang kurang

1. Periksa kartu `Data readiness` di bagian atas. Kartu ini menampilkan persentase kelengkapan dan mengelompokkan item yang kurang ke dalam Personal data, Payroll, BPJS, dan Contract.
2. Pilih satu kelompok, lalu pilih item yang kurang. EcoSystem membuka tab yang tepat dan menyorot kolomnya.
3. Isi datanya.
4. Pilih `Save Changes`. Kartu `Data readiness` diperbarui setelah Anda menyimpan.

Item bertanda `HR` hanya dapat diisi oleh HR. Kelompok yang menampilkan `With HR` berarti tidak ada lagi yang perlu Anda isi.

### Menambahkan foto profil dan tanda tangan

1. Buka tab `Basic Data` lalu gulir ke `Profile photo`.
2. Pilih `Choose photo` dan pilih berkas JPG, PNG, atau WEBP maksimal 4 MB.
3. Geser dan perbesar gambar agar pas dengan lingkaran, lalu simpan. Pilih `Remove photo` untuk menghapusnya.
4. Pada kotak `Signature`, gambar tanda tangan Anda atau unggah gambar (PNG atau JPG, maksimal 1 MB). Hanya Anda yang dapat menambah atau mengubahnya; HR dapat menghapusnya.

Foto Anda kemudian tampil di bilah atas.

### Melihat Contract dan rekap gaji

- Tab `Contract` menampilkan riwayat Contract dan rekap gaji baca-saja. Keduanya dikelola oleh HR.
- `Basic Payment` menampilkan rekap baca-saja dari Paystub terbaru Anda. Unduh dari `Paystub` (lihat [[my-paystub]]).

### Menandatangani atau memeriksa Contract

Saat HR mengirimkan Contract untuk ditandatangani, tab `Contract` menampilkan tahap penandatanganannya beserta tautan untuk membukanya, dan Anda dapat menerima Notification pengingat. Buka Contract-nya, baca, lalu ikuti pilihan di layar untuk menandatangani atau meminta perubahan. Penandatanganan memakai tanda tangan yang tersimpan di profil Anda, jadi tambahkan terlebih dahulu (lihat di atas).

## Saat HR mengunci profil Anda

Setelah HR memverifikasi data Anda, halaman menampilkan spanduk `Your profile is verified and locked by HR.` Tab `Basic Data`, `Address`, `Identification`, `Bank Account`, dan `HR Profile` menjadi `View Only`. Untuk mengubah apa pun, hubungi HR dan minta mereka membuka kunci profil Anda.

> [!IMPORTANT]
> Periksa rekening bank dan data pajak Anda dengan teliti sebelum HR memverifikasinya. Setelah terkunci, Anda tidak dapat memperbaikinya sendiri.

## Pertanyaan umum

**Ada tab yang tidak terlihat.** Akses Anda tidak mencakupnya. Lihat [[access-and-menus]].

**Mengapa sebuah kolom berwarna abu-abu?** Tab tersebut `View Only`, baik karena role Anda maupun karena HR mengunci profil.

**Bagaimana cara mengganti kata sandi?** Buka `Settings` › `Security`. Lihat [[navigating-ecosystem]].

## Topik terkait

- [[access-and-menus]]
- [[navigating-ecosystem]]
- [[my-paystub]]

<!-- sources: routes/web.php, app/Http/Controllers/ProfileController.php, app/Http/Controllers/SettingsController.php, resources/views/master/employee/show.blade.php, resources/views/master/employee/sections/hrprofile.blade.php, resources/views/master/employee/sections/basicdata.blade.php, resources/views/master/employee/sections/payment-recap.blade.php, app/Http/Controllers/MyContractController.php -->

---
key: inventory-assets
chapter: human-capital
order: 120
title: Inventory & Asset
summary: Kelola stok perlengkapan dan daftar Asset perusahaan, serta lihat yang perlu perhatian.
requires: [general.inventory.overview, general.inventory.items, general.inventory.assets, general.inventory.settings]
updated: 2026-10-27
---
`Inventory & Assets` menyimpan dua daftar: perlengkapan habis pakai beserta stoknya, dan Asset perusahaan dengan satu baris per unit. Ikhtisar menampilkan total serta hal yang perlu perhatian.

## Siapa yang menggunakan

Staf GA dan HR yang mengelola perlengkapan dan peralatan.

## Sebelum Anda mulai

Buka `Human Capital` › `Inventory & Assets`. Ada hingga empat tab sesuai akses: `Overview`, `Inventory`, `Assets`, dan `Settings`. Tombol untuk menambah, mengubah, atau menghapus hanya tampil bila Anda punya izin tersebut.

Atur dahulu pilihan dropdown di `Settings` agar formulir menawarkan kategori, satuan, dan lokasi yang tepat.

## Langkah demi langkah

### Membaca ikhtisar

`Overview` menampilkan total dan daftar. Perhatikan `Inventory by category` (nilai adalah harga satuan dikali jumlah stok), `Assets by category` (nilai adalah harga pembelian), `Assets by status`, dan `Needs attention`, yang mendaftar stok menipis atau habis, Asset rusak, dan Asset yang sedang diperbaiki.

### Menambah barang perlengkapan

1. Buka `Inventory` lalu pilih `New Inventory Item`.
2. Isi `Item code`, `Item name`, `Category`, `Unit`, `Qty`, `Minimum stock`, dan `Unit price (Rp)`. Tambahkan `Location`, `Status`, `Notes`, dan foto bila perlu.
3. Simpan.

Daftar menampilkan `Qty`, `Min. stock`, `Unit price`, dan `Stock value`. Statusnya `In stock`, `Low stock`, `Out of stock`, atau `Inactive`. Status dihitung dari jumlah stok kecuali Anda mengaturnya secara manual.

### Menambah Asset

1. Buka `Assets` lalu pilih `New Asset`.
2. Isi `Asset code`, `Asset name`, `Category`, `Brand / make`, `Serial number`, `Assignee`, `Purchase date`, dan `Purchase price (Rp)`.
3. Atur `Condition`, `Asset status`, dan `Location`, serta tambahkan `Notes`.
4. Simpan.

Untuk menyerahkan Asset kepada seseorang, ubah Asset tersebut lalu pilih `Assignee`. Daftar menampilkan `Assignee`, `Condition`, dan `Status`. Cari dan filter dengan ikon corong di judul kolom.

### Mengubah daftar dropdown

1. Buka `Settings` dan pilih daftar: Inventory `Category` dan `Unit`, Assets `Category`, `Condition`, dan `Status`, atau `Location` (dipakai bersama oleh kedua daftar).
2. Pilih `Add Option`, isi `Name` dan `Badge colour`, lalu simpan.
3. Gunakan `Move up` dan `Move down` untuk mengurutkan opsi, matikan opsi dengan `Active`, atau hapus opsi yang tidak dipakai.

> [!NOTE]
> Mengganti nama opsi juga mengganti namanya pada setiap catatan yang memakainya.

## Apa yang terjadi selanjutnya

Gunakan kolom `Assignee` untuk melihat Asset apa yang dipegang seseorang, misalnya saat orang itu keluar, lihat [[offboarding]].

## Pertanyaan umum

**Bisakah saya menghapus barang?** Bisa, dengan izin hapus. Opsi yang dipakai oleh catatan tidak dapat dihapus.

**Di mana foto disimpan?** Bersama catatannya, dan hanya orang yang punya akses ke tab tersebut yang dapat membukanya.

## Topik terkait

- [[offboarding]]
- [[employee-data]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/inventory/overview.blade.php, resources/views/hr-general/inventory/items.blade.php, resources/views/hr-general/inventory/assets.blade.php, resources/views/hr-general/inventory/settings.blade.php, resources/views/hr-general/inventory/components/asset-modal.blade.php, resources/views/hr-general/inventory/components/item-modal.blade.php, app/Models/InventoryOption.php -->

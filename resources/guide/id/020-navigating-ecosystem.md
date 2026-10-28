---
key: navigating-ecosystem
chapter: getting-started
order: 20
title: Menjelajahi EcoSystem
summary: Kenali Dashboard, sidebar, Notification, menu profil, dan Settings pribadi agar Anda cepat menemukan halaman yang dibutuhkan.
requires: []
updated: 2026-10-27
---
Topik ini menunjukkan bagian-bagian utama layar dan cara menyesuaikannya, supaya Anda dapat menuju halaman mana pun dengan cepat.

## Siapa yang menggunakan

Semua orang yang masuk ke EcoSystem. Menu yang tampil di sidebar bergantung pada akses Anda (lihat [[access-and-menus]]).

## Tampilan layar secara garis besar

- **Sidebar** di sebelah kiri: semua menu yang dapat Anda gunakan.
- **Bilah atas**: tombol menu, judul halaman, ikon suara dan lonceng, serta menu di bawah nama Anda.
- **Area halaman**: halaman yang sedang Anda buka.

Di ponsel atau jendela yang sempit, sidebar disembunyikan. Pilih tombol menu di kiri atas untuk membukanya.

## Dashboard

`Dashboard` adalah item pertama di sidebar dan halaman tujuan setelah Anda masuk. Halaman ini menyapa Anda dengan nama. Jika Anda dapat memakai `My Attendance`, kartu sapaan juga memiliki tombol `Check-in` dan `Check-out`. Di bawahnya mungkin tampil:

- `Company overview` dan kartu ringkasan lain, hanya untuk area yang dapat Anda akses.
- Panel pintasan dengan dua tab, `Favorite` dan `All Menu`.
- Ringkasan Attendance Anda untuk bulan berjalan.

## Langkah demi langkah

### Mencari menu

1. Klik kotak `Search menu...` di bagian atas sidebar.
2. Ketik sebagian nama menu. Sidebar hanya menampilkan menu yang cocok.
3. Pilih menunya, atau hapus pencarian dengan tombol `x`.

### Melipat sidebar

1. Pilih judul bagian, misalnya `My Workspace`, untuk melipat atau membukanya. EcoSystem mengingat pilihan Anda di peramban ini.
2. Pilih tombol panah ganda di samping kotak pencarian untuk melipat atau membuka semua bagian sekaligus.
3. Pilih `Collapse to icons` di bagian bawah sidebar untuk mengecilkannya menjadi deretan ikon. Arahkan kursor atau pilih ikon bagian untuk melihat menunya. Pilih `Expand sidebar` untuk mengembalikannya.

### Menyematkan menu favorit

1. Arahkan kursor ke sebuah menu di sidebar. Ikon pin akan muncul.
2. Pilih pin tersebut (`Pin to sidebar`). Pilih lagi untuk melepasnya (`Unpin from sidebar`).
3. Buka `Dashboard` dan pilih tab `Favorite` untuk menuju menu yang Anda sematkan dengan satu klik. Tab `All Menu` menampilkan semua menu yang dapat Anda buka.

> [!NOTE]
> Anda dapat menyematkan hingga 6 menu. Jika batasnya tercapai, lepas salah satu terlebih dahulu.

### Memeriksa Notification

1. Pilih ikon lonceng di bilah atas. Angka merah menunjukkan jumlah Notification yang belum dibaca.
2. Pilih sebuah Notification untuk membuka halaman terkait.
3. Pilih `Mark all read` untuk membersihkan angkanya, atau `View all` untuk membuka halaman `Notifications`.
4. Di halaman `Notifications`, gunakan tab `All`, `Unread`, dan `Read` serta kotak pencarian untuk menemukan item yang lebih lama.

Ikon pengeras suara di samping lonceng menyalakan atau mematikan suara Notification.

### Memakai menu di bawah nama Anda

Pilih nama Anda di pojok kanan atas untuk membuka `My Profile`, `Settings`, `User Guide`, atau `Sign Out`.

### Menyesuaikan Settings pribadi

1. Buka `Settings` dari sidebar atau dari menu di bawah nama Anda.
2. Pilih tab di sebelah kiri:
   - `Appearance`: `Theme` (Light, Dark, atau Auto), `Accent color`, `Sidebar style` (Gradient atau Solid), `Font size`, `Compact mode`, dan `Show animations`.
   - `Preferences`: `Auto-save`, `Auto-refresh`, dan `Keyboard shortcuts`.
   - `Notifications`: menyalakan atau mematikan Notification, peringatan email dan push, serta memilih suara Notification.
   - `Security`: mengganti kata sandi. Masukkan kata sandi baru minimal 8 karakter, konfirmasikan, lalu pilih `Update password`.
   - `Language & Region`: `Language` (English atau Bahasa Indonesia), `Timezone`, dan `Date format`.
3. Pilih `Save changes`. Pilih `Reset` pada tab `Appearance` untuk kembali ke pengaturan awal.

> [!TIP]
> Suara Notification disimpan di peramban Anda, jadi Anda perlu mengaturnya lagi di setiap peramban atau komputer baru.

## Pertanyaan umum

**Menu yang saya butuhkan tidak ada di sidebar.** Role Anda mungkin belum mencakupnya. Lihat [[access-and-menus]].

**Sidebar terlalu lebar atau terlalu ramai.** Gunakan `Collapse to icons`, lipat bagian yang jarang dipakai, atau sematkan menu favorit Anda.

## Topik terkait

- [[access-and-menus]]
- [[statuses-and-approvals]]
- [[my-profile]]
- [[welcome]]

<!-- sources: resources/views/dashboard.blade.php, resources/views/partials/sidebar.blade.php, resources/views/partials/sidebar-section.blade.php, resources/views/home/home.blade.php, resources/views/home/command-center.blade.php, resources/views/hr-general/dashboard/attendance.blade.php, resources/views/notifications/index.blade.php, resources/views/settings/index.blade.php, app/Http/Controllers/SettingsController.php -->

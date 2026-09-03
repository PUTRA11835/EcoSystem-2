# Handoff: Delivery Support — Section "Recons" (baru)

> **Catatan tambahan (2026-09-03)**: sesi ini juga mengerjakan satu permintaan
> di LUAR modul Recons — filter + search kolom **"Assign Delivery"** pada Menu
> Ticket. Lihat entri terakhir di bagian Log.
>
> **Status**: 🟡 Rancangan/perencanaan selesai, **belum ada kode yang diubah**. Menunggu
> konfirmasi user sebelum mulai coding (instruksi eksplisit user: "rancangan dulu saja
> ya sama perencanaan").
> **Diminta oleh**: user (chat 2026-09-03), dari screenshot halaman
> `/delivery/support/{id}` (tab General/Approval/Financial/Team/Customer PIC/
> Activities/SLA/Plan Cost yang sudah ada).
> File utama yang akan disentuh: [routes/delivery-support.php](../../routes/delivery-support.php),
> [database/seeders/MenuSeeder.php](../../database/seeders/MenuSeeder.php),
> [resources/views/delivery/support/list/show.blade.php](../../resources/views/delivery/support/list/show.blade.php),
> `resources/views/delivery/support/list/partials/recons.blade.php` (baru),
> `app/Http/Controllers/Delivery/DeliverySupportReconsController.php` (baru),
> `app/Models/DeliverySupportRecons.php` + `DeliverySupportReconsTicket.php` (baru),
> `app/Exports/DeliverySupportReconsExport.php` (baru).

## Requirement asli (verbatim dari user)

> - list tiket belum sesuai harusnya ada banyak
> - kolom yang di tampilkan: Nomor tiket, Description, Start date, Close Date, Status,
>   Type, Customer MD, Recons Number, Recons Description, Recons Status
> - tambahkan tombol New Recons
> - Pada Screen Recons tampilkan screen dengan field: Recons Number, Description,
>   Recons date, Status (Draft, Submit), list tiket dengan kondisi sudah close, ada
>   customer MD dan tiket belum pernah masuk ke Recons sebelumnya
> - pada screen Recons ada tombol Save Draft dan tombol submit
> - user Id yang melakukan recons bisa di simpan
> - perlu buat list Recons dan export ke excel untuk detail tiketnya

Klarifikasi yang sudah didapat dari user sebelum rancangan ini (lewat tanya-jawab):
fungsi Recons = **rekonsiliasi tiket/aktivitas**, ditaruh sebagai **section baru di
dalam detail Delivery Support** (sejajar Financial/Activities/dst, bukan menu
top-level terpisah), dan section ini butuh **view + edit/manage** (bukan cuma
read-only report).

## Interpretasi & pemetaan ke struktur yang ada

- "list tiket belum sesuai harusnya ada banyak" → dibaca sebagai: tab utama Recons
  harus menampilkan **SEMUA** tiket yang terhubung ke Delivery Support ini (sumber
  sama dengan `DeliverySupport::tickets()` — tiket yang ter-link ke Activity lewat
  `delivery_support_activities.ticket_id`), bukan cuma subset. Filter
  closed+ada MD+belum-pernah-di-recons **hanya berlaku di screen "New Recons"**
  (tempat memilih tiket mana yang mau dimasukkan ke satu batch rekonsiliasi), bukan
  di tab utama.
- Ada 2 tampilan berbeda yang diminta, jadi didesain sebagai 2 view dalam 1 section:
  1. **Tab Recons → daftar tiket** (kolom sesuai requirement, termasuk info
     recons kalau tiket itu sudah pernah di-reconcile).
  2. **Tab Recons → daftar batch Recons** ("list Recons" + tombol Export Excel per
     batch) — toggle di dalam section yang sama, mirip pola toggle
     "All Tickets / Unassign" yang sudah ada di
     [resources/views/delivery/support/index.blade.php](../../resources/views/delivery/support/index.blade.php).

## Model data (baru, 2 tabel)

### `delivery_support_recons` (header / 1 batch rekonsiliasi)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| delivery_support_id | FK → `delivery_support`, cascade | |
| recons_number | string, unique per support | lihat "Keputusan terbuka" #1 |
| description | text nullable | |
| recons_date | date | default hari ini |
| status | enum('draft','submitted') | dikontrol lewat aksi (bukan dropdown bebas) |
| created_by_id | FK → `employee`, nullable | **"user Id yang melakukan recons"** — employee yang bikin/terakhir edit draft |
| submitted_by_id | FK → `employee`, nullable | employee yang klik Submit (bisa beda dari created_by) |
| submitted_at | datetime nullable | |
| timestamps | | |

### `delivery_support_recons_tickets` (baris tiket dalam 1 batch)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | PK | |
| delivery_support_recons_id | FK → `delivery_support_recons`, cascade | |
| ticket_id | FK → `ticket` | |
| man_days_snapshot | decimal(8,2) | **snapshot** nilai `ticket.man_days` saat dimasukkan — supaya angka di Recons tidak berubah kalau `man_days` tiket diedit belakangan (penting untuk audit/export) |
| timestamps | | |
| unique(delivery_support_recons_id, ticket_id) | | |
| index(ticket_id) | | dipakai untuk query eligibilitas |

**Aturan eligibilitas "belum pernah masuk Recons"**: tiket dianggap "used" kalau
sudah punya baris di `delivery_support_recons_tickets` untuk **recons manapun**
milik Delivery Support ini, **tanpa peduli status draft/submitted**. Konsekuensi:
- Kalau draft dihapus (`destroy`), baris tiketnya ikut terhapus (cascade) →
  tiket itu otomatis muncul lagi sebagai eligible.
- Kalau draft masih ada (belum disubmit/dihapus), tiketnya TIDAK bisa dipakai di
  draft/recons lain sampai draft itu di-submit atau dihapus. Ini mencegah 1 tiket
  ke-double-hitung di 2 batch berjalan bersamaan.
→ **Perlu dikonfirmasi**: apakah aturan ini sesuai maksud user, atau tiket yang
masih di draft (belum submit) harusnya tetap "boleh dipakai di recons lain"?
(Saya sarankan aturan di atas — lebih aman dari double-count — tapi ini keputusan
bisnis, bukan teknis.)

## Query "New Recons" — daftar tiket eligible

```
Ticket::whereIn('ticket_id', $support->tickets()->pluck('ticket_id'))
    ->where('status', 'closed')
    ->whereNotNull('man_days')->where('man_days', '>', 0)
    ->whereNotIn('ticket_id',
        DeliverySupportReconsTicket::whereHas('recons',
            fn($q) => $q->where('delivery_support_id', $support->id)
        )->pluck('ticket_id')
    )
    ->get();
```

## Routes (ditambahkan ke `routes/delivery-support.php`, di dalam grup `{support}`)

```
Route::prefix('recons')->name('recons.')->group(function () {
    Route::middleware('menu:delivery-support.recons.view')->group(function () {
        Route::get('/tickets',            ...)->name('tickets');           // tab utama: semua tiket + kolom recons
        Route::get('/eligible-tickets',   ...)->name('eligible-tickets');  // untuk screen New Recons
        Route::get('/batches',            ...)->name('batches.index');    // list Recons (batch)
        Route::get('/batches/{recons}',   ...)->name('batches.show');     // detail 1 batch
        Route::get('/batches/{recons}/export', ...)->name('batches.export'); // Excel
    });
    Route::middleware('menu:delivery-support.recons.manage')->group(function () {
        Route::post('/batches',               ...)->name('batches.store');   // create baru (Save Draft pertama kali)
        Route::delete('/batches/{recons}',    ...)->name('batches.destroy'); // hapus draft (submitted tidak boleh dihapus)
    });
    Route::middleware('menu:delivery-support.recons.edit')->group(function () {
        Route::put('/batches/{recons}',        ...)->name('batches.update'); // edit draft (Save Draft berikutnya)
        Route::post('/batches/{recons}/submit',...)->name('batches.submit'); // aksi Submit, transisi status
    });
});
```

Ini mirror persis pola Payment Terms/Plan Cost yang sudah ada (view/edit/manage
terpisah, `menu:` middleware per grup) — tidak ada pola baru yang diperkenalkan.

## Permission/menu (`MenuSeeder.php`)

Tambah section baru (mengikuti array section yang sudah ada di baris ~49-57):
```php
['base' => 'delivery-support.recons', 'name' => 'Recons', 'actions' => ['view', 'edit', 'manage']],
```
Role grant default diusulkan mirror section lain di Delivery Support (ADMIN, HOS,
HELPDESK, RPMO — sama seperti `delivery-support.add-new`/`delete-support` di baris
~400-401) — **perlu dikonfirmasi**: apakah Submit butuh role lebih terbatas
(mis. hanya Support Manager/Head) dibanding Save Draft?

## UI

1. **Tab nav** di `show.blade.php` (~baris 170): tombol baru "Recons", guarded
   `@if($can('delivery-support.recons.view'))`, mengikuti pola tab lain persis.
2. **Section Recons** (partial baru `partials/recons.blade.php`, di-include seperti
   `partials/plancost.blade.php`):
   - Toggle "Tickets" / "Recons List" (2 tampilan, 1 section — pola sama seperti
     toggle All/Unassign di `delivery/support/index.blade.php`).
   - **View "Tickets"**: tabel dengan 10 kolom sesuai requirement user, tombol
     **"New Recons"** di pojok kanan atas (guarded `.recons.manage`).
   - **View "Recons List"**: tabel batch (Recons Number, Description, Recons Date,
     Status badge Draft/Submitted, Created By, jumlah tiket, total MD, tombol
     **Export Excel** per baris, tombol View untuk buka detail batch).
3. **Screen "New Recons"** — perlu keputusan: **modal** (konsisten dengan modal
   edit section lain di halaman ini) vs **halaman terpisah** (konsisten dengan
   "Open Planning" yang navigasi ke halaman sendiri). Karena daftar tiket
   eligible "harusnya ada banyak" (butuh scroll/search/select banyak baris),
   saya sarankan **halaman terpisah** (`GET .../recons/create`) — lebih lega
   daripada modal. Field: Recons Number (lihat keputusan #1), Description,
   Recons Date, badge Status (read-only, bukan dropdown — ditentukan tombol mana
   yang diklik), tabel checklist tiket eligible (search + select-all), tombol
   **Save Draft** dan **Submit** di footer.

## Export Excel

`App\Exports\DeliverySupportReconsExport` (pola sama seperti
`CollectionOutlookSupportExport` — `FromArray` + `ShouldAutoSize`, kalau perlu
styling status pakai `WithEvents`). Header info batch (Recons Number/Date/Status/
Created By) di baris atas, lalu tabel detail tiket: Ticket Number, Description,
Start Date, Close Date, Status, Type, Customer MD.

## Keputusan — SUDAH FINAL (dikonfirmasi user 2026-09-03)

1. **Sumber "Close Date" — ditemukan sudah ada, TIDAK perlu kolom/migration baru.**
   `ticket.end_date` **sudah** dipakai sebagai Close Date oleh aplikasi saat ini:
   `TicketController::updateStatus()` ([TicketController.php:2991-2996](../../app/Http/Controllers/TicketController.php#L2991-L2996))
   menulis `end_date = now()` begitu `status` berubah jadi `closed`, dan
   di-`null`-kan lagi kalau tiket di-reopen ("Cleared on reopen so a stale close
   date doesn't linger" — komentar asli di kode). Kolom ini ditampilkan persis
   sebagai "Close Date" di [ticket/index.blade.php:333-336](../../resources/views/ticket/index.blade.php#L333-L336).
   → **Recons tinggal reuse `ticket.end_date` untuk kolom Close Date** (hanya
   bermakna kalau `status === 'closed'`, yang memang jadi salah satu syarat
   eligibilitas). **Tidak ada perubahan apa pun ke tabel `ticket`.**
   (Catatan implikasi yang perlu diingat, bukan untuk diubah: field `end_date`
   dipakai dobel — sebagai target/due date sebelum closed, dan sebagai actual
   close date sesudah closed. Ini perilaku existing yang sudah dipakai di
   `delivery/support/index.blade.php` juga untuk hitung "Overdue"/"Due in X
   days" — di luar scope Recons, tidak disentuh.)
2. **Screen New Recons: halaman terpisah** (bukan modal) — dikonfirmasi.
3. **Aturan eligibilitas draft: TIDAK BOLEH** — tiket yang masih nyangkut di
   draft manapun (belum submit/belum dihapus) tidak eligible dipakai di
   Recons lain — dikonfirmasi.
4. **Format `recons_number`: hybrid** — dikonfirmasi. Field di screen New Recons
   dibuat **opsional**:
   - Kalau dikosongkan → sistem **auto-generate** (format diusulkan:
     `RCN-{delivery_support_id}-{urutan 3 digit, per support}`, mis.
     `RCN-6-001`, `RCN-6-002`, ...). Generate terjadi saat **Save Draft**
     pertama kali disimpan (bukan saat halaman dibuka), supaya nomor urut tidak
     "bolong" kalau user buka halaman lalu batal tanpa save.
   - Kalau diisi manual → dipakai apa adanya, divalidasi **unique per
     `delivery_support_id`** (2 support boleh punya nomor sama, tapi tidak
     dalam 1 support yang sama).
   → Format persis (`RCN-{id}-{urutan}`) masih usulan saya berdasarkan pola
   penomoran lain di app (mis. IO Number) — kalau user punya format spesifik
   yang harus diikuti, tolong infokan sebelum saya implementasikan; kalau tidak
   ada masukan lebih lanjut saya pakai format usulan ini.

## Keputusan tersisa (kecil, akan saya putuskan sendiri konsisten dengan pola
existing kecuali user koreksi)

- **Role default untuk aksi Submit**: disamakan dengan role yang dapat
  `.edit`/`.manage` section Recons (ADMIN, HOS, HELPDESK, RPMO — mirror grant
  `delivery-support.add-new`/`delete-support`), tidak dibuat lebih restriktif,
  karena user tidak eksplisit minta pembatasan lebih jauh. Bisa direvisi kalau
  ternyata perlu role terpisah untuk Submit vs Save Draft.

## Risiko & non-interferensi

- Semua tabel/route/controller/permission slug baru — **tidak mengubah** tabel
  `delivery_support`, `delivery_support_activities`, atau file shared
  (`custom-dropdown.js`) kecuali pemakaian aditif standar (`.custom-dd` untuk
  filter/search di tabel tiket, pola yang sama seperti section lain).
- **Tidak ada perubahan sama sekali ke tabel `ticket`** — Close Date reuse kolom
  `end_date` yang sudah ada & sudah diisi oleh flow existing (`updateStatus()`),
  murni query read-only dari sisi Recons.
- `MenuSeeder.php` hanya ditambah entri baru (aditif), tidak mengubah entri
  section lain yang sudah ada.

## Log

- 2026-09-03 — Rancangan awal dibuat berdasarkan requirement + screenshot user.
  **Belum ada kode yang diubah.**
- 2026-09-03 (lanjutan) — 4 dari 5 keputusan terbuka dikonfirmasi user (lihat
  "Keputusan — SUDAH FINAL" di atas). Poin Close Date ternyata **sudah ada**
  di kode (`ticket.end_date`, diisi oleh `TicketController::updateStatus()`),
  ditemukan setelah user minta dicek ulang — bukan perlu kolom baru seperti
  dugaan awal saya. Rancangan sudah lengkap & konsisten dengan pola existing.

- 2026-09-03 (implementasi) — user instruksi: kerjakan semua, jangan menyenggol
  fitur lain, **route hanya GET/POST** (produksi HTTPS bisa memblokir verb
  lain). **Implementasi SELESAI**, 37/37 uji fungsional lolos, seluruh data uji
  dibersihkan (`delivery_support_recons` kembali 0 baris).

  **File baru**: 2 migration (tabel + menu), 2 model
  (`DeliverySupportRecons`, `DeliverySupportReconsTicket`), 1 controller
  (`Delivery\DeliverySupportReconsController`), 1 exception
  (`ReconsValidationException`), 1 export (`DeliverySupportReconsExport`),
  4 view (partial section + partial script + halaman form + halaman detail).
  **File disentuh**: `routes/delivery-support.php` (tambah grup route),
  `MenuSeeder.php` (tambah section + override admin-only), `DeliverySupport.php`
  (tambah relasi `recons()`), `show.blade.php` (tambah tab + section + include
  script — 3 sisipan, tidak ada baris lama yang diubah).

  **Keputusan yang berubah/ditemukan saat implementasi** (berbeda dari rancangan
  awal, semua ada alasannya):
  1. **Grant izin = EC Administrator saja**, bukan mirror role lain seperti
     rencana awal. Alasan: [MenuRegistrar](../../app/Support/MenuRegistrar.php)
     punya aturan baku tertulis "menu/slug izin BARU selalu lahir aktif HANYA
     untuk EC Administrator" supaya izin tidak menyebar diam-diam ke role yang
     tak pernah disetujui. Role lain diberikan lewat Control Center → Menu
     Access. `MenuSeeder` juga diberi override admin-only untuk 3 slug ini
     supaya install baru & DB produksi berperilaku identik.
  2. **Kunci "sudah pernah masuk Recons" dibuat GLOBAL**, bukan per Delivery
     Support seperti rancangan awal. Alasan: satu `ticket_id` secara skema bisa
     ter-link ke activity di lebih dari satu support (tidak ada constraint yang
     mencegah), sehingga scope per-support memungkinkan tiket yang sama
     direkonsiliasi 2× di tempat berbeda (dobel hitung MD) DAN membuat baris
     tabel terduplikasi saat join. Ini juga lebih sesuai bunyi literal
     requirement user ("tiket belum pernah masuk ke Recons sebelumnya").
  3. **Tidak ada PUT/PATCH/DELETE sama sekali**. Update memakai
     `POST .../{recons}/save`, hapus `POST .../{recons}/delete`, submit
     `POST .../{recons}/submit`. Berbeda dari section Plan Cost yang memakai
     header `X-HTTP-Method-Override: PUT` — di sini dihindari total sesuai
     permintaan user.
  4. **URL di JS dibuat relatif** (`/delivery/support/${id}/recons`), mengikuti
     konvensi `financial-plancost-scripts.blade.php`, bukan `url()`/`route()`
     absolut — aman untuk HTTPS di belakang proxy.

  **Verifikasi yang sudah dijalankan** (bukan cuma baca kode):
  - `php -l` seluruh file PHP baru/berubah → OK.
  - Blade `compileString()` + `php -l` untuk 5 view → OK.
  - `php artisan migrate` di DB lokal → 2 migration jalan, tabel & 3 slug menu
    terbentuk dengan grant EC Administrator saja.
  - `php artisan route:list --path=recons` → 11 route, semuanya GET/POST.
  - **37 uji fungsional** lewat controller nyata terhadap data asli support #8
    (38 tiket, 16 eligible): daftar tiket lengkap & tanpa duplikat, filter
    eligibilitas, auto-number `RCN-8-001`, nomor manual, tolak nomor duplikat,
    tolak tiket milik batch lain, rollback transaksi saat gagal, snapshot MD,
    edit draft (tambah/lepas tiket), submit, penguncian setelah submit
    (edit/hapus/submit ulang semuanya 422), guard batch milik support lain
    (404), hapus draft + tiket kembali eligible, isi file Excel.
  - Render halaman: Support Details (support #6 & #8) + form new/edit + detail
    draft/submitted → semua render tanpa error, tombol muncul/hilang sesuai
    status, dan **tanpa izin `recons.view` tab/section/script tidak ikut
    ter-render sama sekali**.

  **TEMUAN DATA yang perlu keputusan user (bukan bug kode)**: dari 1.113 tiket
  berstatus `closed` di DB, **hanya 1 yang punya `end_date`** terisi. Artinya
  kolom **Close Date akan tampil "-" untuk hampir semua tiket lama**. Penyebab:
  pengisian `end_date` saat close baru ditambahkan belakangan di
  `TicketController::updateTicketStatus()` (dikonfirmasi sebagai SATU-SATUNYA
  jalur yang mengubah status tiket, jadi tiket yang di-close mulai sekarang
  pasti dapat Close Date). Opsi untuk data lama: (a) biarkan "-", (b) backfill
  dari audit log kalau riwayat perubahan statusnya tersimpan, (c) backfill
  perkiraan dari `updated_at`. **Belum saya sentuh sama sekali** — perubahan
  data historis produksi harus diputuskan user.

  **BELUM diverifikasi di browser** — sesi ini tidak punya akses browser
  interaktif. Yang perlu dicek user: buka `/delivery/support/8`, klik tab
  Recons, cek tabel 10 kolom, toggle Tickets ↔ Recons List, tombol New Recons,
  centang tiket + Save Draft, buka draft → Submit, lalu Export Excel.

- 2026-09-03 (revisi setelah uji browser user) — user sudah mencoba di browser
  (`/delivery/support/73/recons/create`, export dari support #36) dan memberi
  3 koreksi. Semuanya sudah dikerjakan:

  1. **Close Date dikonfirmasi user SUDAH BENAR** — `ticket.end_date` memang
     tanggal saat status diganti ke `closed`. Tidak ada perubahan kode. Catatan
     temuan sebelumnya (banyak tiket lama `end_date` NULL) tetap berlaku
     sebagai kondisi data, bukan bug — user menyatakan tidak masalah.

  2. **Konfirmasi & notifikasi disamakan dengan komponen aplikasi.**
     Sebelumnya saya memakai `confirm()` bawaan browser (kotak dialog abu-abu
     yang muncul di screenshot user) dan modal hapus buatan sendiri. Ternyata
     aplikasi ini SUDAH punya komponen global:
     - [partials/confirm-modal.blade.php](../../resources/views/partials/confirm-modal.blade.php)
       → `await showConfirm(pesan, judul, 'danger'|'primary'|'default', {okText})`,
       yang di file itu sendiri disebut "replaces browser native confirm()
       **everywhere**" — jadi pemakaian `confirm()` saya memang menyalahi
       konvensi yang sudah ditetapkan.
     - `showToast()` / `showNotification()` dari
       [dashboard.blade.php](../../resources/views/dashboard.blade.php) (toast
       pojok layar dengan progress bar).
     Perubahan: seluruh `confirm()` diganti `showConfirm()` (Submit memakai
     varian `primary`, Hapus draft varian `danger`), modal hapus buatan sendiri
     **dihapus total** dari `partials/recons.blade.php` (≈30 baris markup +
     3 fungsi JS) karena sudah digantikan modal global, dan helper `notify()`
     disamakan persis dengan `showPlanCostToast()` milik section Plan Cost
     (`showToast` → `showNotification` → `alert` sebagai urutan fallback).
     Dialog Submit kini juga menyebut jumlah tiket & total MD supaya user tahu
     persis apa yang akan dikunci.

  3. **Export Excel diperbaiki — ada BUG nyata + desain dirapikan.**
     *Bug*: pada screenshot user, yang berwarna merah adalah **baris data**,
     bukan baris header. Penyebab: baris kosong `[]` pada array export TIDAK
     menempati baris di sheet (PhpSpreadsheet melewatinya), sedangkan posisi
     header saya hitung manual dari `count($out)` — meleset satu baris,
     sehingga styling header mendarat di baris tiket pertama. Perbaikan: posisi
     header sekarang **dicari dari isi sel** (`findHeaderRow()` memindai kolom A
     mencari teks "Ticket Number"), jadi tidak mungkin meleset lagi berapa pun
     jumlah baris informasi di atasnya.
     *Desain* (tabel dengan 7 kolom sesuai permintaan user):
     - Judul dokumen + nama support/customer di 2 baris teratas.
     - Blok informasi batch: label abu (gray-500) + nilai tebal; nilai Status
       diberi warna semantik (hijau `#166534` untuk Submitted, kuning
       `#92400E` untuk Draft) — sama dengan badge di halaman web.
     - Header tabel merah brand `#991B1B` (warna primary aplikasi, bukan warna
       acak) + teks putih tebal, rata tengah, tinggi baris 24.
     - Baris data: garis tipis `#E5E7EB`, zebra striping `#F9FAFB`, perataan
       per tipe data (teks kiri, tanggal/status/tipe tengah, MD kanan dengan
       format `0.00`), deskripsi `wrapText`.
     - Baris Total MD: tebal, latar amber `#FEF3C7`, garis atas tipis + garis
       bawah ganda warna brand.
     - Lebar kolom ditetapkan eksplisit (`ShouldAutoSize` dilepas supaya kolom
       Description tidak melar tak terkendali), `freezePane` di bawah header,
       dan `setAutoFilter` pada rentang tabel.
     Semua kontras teks/latar di atas rasio 4.5:1 (WCAG AA).

  **Verifikasi revisi**: `php -l` + Blade compile OK; JS hasil render
  divalidasi `node --check` (3 halaman, semuanya OK); dipastikan tidak ada lagi
  `confirm()`/`alert()` langsung di file Recons; 37/37 uji fungsional tetap
  lolos; isi file Excel dibaca ulang dan dipastikan **baris 12 = header merah,
  baris 13-16 = data (striping benar), baris 17 = total amber**, freeze pane
  `A13`, autofilter `A12:G16`.
  Data Recons hasil uji browser user (RCN-77-001, RCN-77-002, RCN-36-001)
  sengaja TIDAK disentuh.

- 2026-09-03 (revisi kedua setelah uji browser) — user memberi 6 permintaan
  lanjutan. Semuanya sudah dikerjakan; 59 uji fungsional (37 suite lama + 22
  suite baru) lolos.

  1. **Format nomor baru: `MDRC-[customer_code]-[yymm]-[xxxx]`.**
     - `customer_code` dari master Business Partner (`customer.customer_code`),
       dipakai apa adanya tanpa batas panjang (data nyata: 2–11 karakter);
       hanya karakter non-alfanumerik yang dibuang supaya struktur 4 segmen
       nomor tidak rusak. Support tanpa kode memakai `NA`.
     - `yymm` mengikuti **tanggal Recons**, bukan tanggal hari ini.
     - Counter **GLOBAL lintas customer**, reset tiap tahun (keputusan user).
       Contoh nyata dari uji: `MDRC-AIRNAV-2609-0001` lalu customer lain dapat
       `MDRC-SML-2609-0002`; ganti tahun → `MDRC-AIRNAV-2701-0001`.
     - Counter dihitung dari nomor tahun berjalan yang sudah terbit (bukan
       COUNT baris), jadi draft yang dihapus tidak membuat nomor dipakai ulang.
     - **Field nomor dihapus dari form** — tidak diisi manual lagi. Nilai
       `recons_number` yang dikirim client sekarang diabaikan controller.
     - Nomor terbit **sekali** saat pembuatan dan tidak berubah walau draft
       disimpan ulang / tanggalnya diedit, supaya identitas dokumen stabil.
     - Karena counter global, keunikan nomor tidak cukup dijaga per support:
       migrasi `2026_09_03_000003` memindahkan unique index dari
       `(delivery_support_id, recons_number)` ke `(recons_number)`. Migrasi
       menolak jalan (dengan pesan jelas) bila menemukan nomor kembar.
     - Konkurensi: dua penyimpanan bersamaan bisa menghasilkan counter sama;
       ditangkap unique index lalu **dicoba ulang otomatis** sampai 5 kali
       (`createWithGeneratedNumber()`), bukan dikunci lock tabel.
     - Data lama user (RCN-77-001 dst) dibiarkan apa adanya — format lama tetap
       valid sebagai riwayat, tidak ikut di-rename.

  2. **Export: baris "Created Date"** ditambahkan tepat di bawah "Created By".

  3. **Tombol Cancel / Save Draft / Submit dipindah ke toolbar atas**, sebaris
     dengan kotak pencarian dan bersebelahan dengan "Clear selection"; bar aksi
     di bagian bawah halaman dihapus. Diberi pemisah vertikal tipis antara aksi
     tabel (Select all shown / Clear selection) dan aksi dokumen.

  4. **Filter tanggal pada daftar tiket** (memakai komponen `.custom-dd`
     bawaan aplikasi, bukan komponen baru — `custom-dropdown.js` hanya
     *dipakai*, tidak diubah):
     - Pemilih **dasar tanggal**: Close Date / Start Date. Alasan disediakan
       pilihan (bukan dikunci ke Close Date): mayoritas tiket lama `end_date`-
       nya masih NULL sehingga filter Close Date saja sering menghasilkan
       kosong. Disetujui user.
     - **Bulan/tahun multi-pilih** (opsi dibangun otomatis dari data tiket yang
       ada, terurut terbaru dulu) + tombol Clear.
     - **Rentang tanggal** (from–to) yang bisa dipakai bersamaan dengan pilihan
       bulan (digabung secara AND).
     - "Select all shown" mengikuti hasil filter, jadi user bisa memfilter satu
       periode lalu langsung mencentang semuanya sekaligus.
     - Ada keterangan kecil "N of M shown • X ticket(s) have no close date"
       supaya tidak bingung saat hasil filter kosong karena tanggalnya memang
       belum terisi.

  5. **Aksi Cancel di Recons List** (dan di halaman detail, untuk simetri
     dengan tombol Submit): batch `submitted` dikembalikan ke `draft`, jejak
     `submitted_by_id`/`submitted_at` dibersihkan, nomor & daftar tiket tetap.
     Setelah cancel, draft bisa diedit lagi. Tiket di dalamnya **tetap
     terkunci** dari Recons lain baik saat draft maupun submitted (sesuai
     permintaan user "ketika draft, tiket dalam draft tidak bisa dipilih") —
     perilaku ini memang sudah berlaku sejak awal dan dipastikan lewat uji.
     Route baru: `POST .../{recons}/cancel` (izin `.edit`, sama dengan submit).

  6. **Close Date jadi passthrough murni** dari `ticket.end_date` — syarat
     tambahan `status === 'closed'` dihapus, jadi persis sama dengan kolom
     "Close Date" di Menu Ticket. Dikonfirmasi lewat kode: Menu Ticket memang
     merender `ticket.end_date` apa adanya
     ([ticket/index.blade.php:1486](../../resources/views/ticket/index.blade.php#L1486))
     dan uji membuktikan nilai yang tampil identik dengan isi tabel (mis.
     tiket 26080146: DB `2026-08-12` → tampil `12 Aug 2026`).

  **Verifikasi revisi kedua**: `php -l` semua file PHP OK; migrasi ketiga jalan
  bersih; `route:list` 12 route, tetap **hanya GET/POST**; JS 3 halaman lolos
  `node --check` dan tidak ada `confirm()`/`alert()` native; render halaman
  memastikan tombol pindah ke toolbar, filter tanggal muncul, dan input nomor
  manual benar-benar hilang; suite lama 37/37 + suite revisi 22/22 lolos,
  seluruh data uji dibersihkan (7 baris sisa adalah data uji browser user).

- 2026-09-03 (revisi ketiga) — 4 temuan user dari uji browser. Total uji
  sekarang 68 (37 + 22 + 9), semuanya lolos.

  1. **"Customer code jadi SML padahal di master SINERGI" — BUKAN bug kode,
     melainkan DATA GANDA di master.** Ditemukan dua record customer dengan
     `name_1` sama persis "Sinergi Mitra Lestari Indonesia":
     `customer_id 103` (code **SML**) dan `customer_id 142` (code **SINERGI**).
     `delivery_support#57` menautkan `client_id = 103`, jadi nomor
     `MDRC-SML-…` memang kode milik customer yang benar-benar ditautkan ke
     support tersebut; halaman Business Partner yang user buka adalah record
     142. Dicek juga: ini **satu-satunya** nama customer yang duplikat di
     seluruh master. Tidak ada data yang saya ubah — perlu keputusan user
     (merge/rename) karena menyangkut master data produksi.
     Meski begitu, ketentuan "tulis apa adanya" tetap diterapkan:
     `customerCodeFor()` kini mengembalikan `customer.customer_code` **persis**
     (hanya `trim()`) — sebelumnya di-`strtoupper()` dan karakter non-alfanumerik
     dibuang. Dicek ke data: saat ini tidak ada satu pun kode yang berubah
     akibat sanitasi lama, jadi perubahan ini tidak menggeser nomor yang sudah
     ada; efeknya untuk kode baru yang memuat huruf kecil/titik/dll.
     Konsekuensinya parser counter tidak boleh lagi memakai `explode('-')`
     (kode bisa memuat tanda hubung) → diganti regex yang di-anchor ke dua
     segmen terakhir `-yymm-xxxx`. Diuji dengan nomor `MDRC-AB-CD-2609-0042`:
     counter berikutnya terbaca benar `0043`.

  2. **Nama file Excel = nomor Recons** — `MDRC-SML-2609-0001.xlsx`, tanpa
     awalan `Recons_` dan tanpa timestamp. Hanya karakter yang dilarang sistem
     berkas (`\ / : * ? " < > |`) yang diganti `_`, supaya kode customer tak
     lazim tetap aman diunduh.

  3. **Filter tanggal "tidak muncul" saat memilih 26 Juni** — akar masalahnya:
     dasar filter masih **Close Date** (nilai default lama) sementara ke-8 tiket
     support tersebut `end_date`-nya NULL, sehingga hasilnya 0 baris. Perbaikan:
     - Default dasar filter diubah ke **Start Date** (kolom yang selalu terisi)
       sehingga filter langsung berfungsi; Close Date tetap bisa dipilih.
     - Pesan tabel kosong kini menjelaskan sebabnya secara spesifik, mis.
       "None of the 8 eligible tickets has a close date, so nothing can match
       this date filter. Switch 'Filter by date' to Start Date, or reset the
       filter." — sebelumnya hanya "No ticket matches", yang membuat filter
       terlihat seperti rusak.

  4. **Opsi ketik bebas untuk tanggal** — kotak pencarian sekarang juga
     mencocokkan tanggal, dalam bentuk mentah (`2026-06-26`), label tampilan
     (`26 Jun 2026`), maupun format lokal (`26/06/2026`), untuk Start Date dan
     Close Date sekaligus. Jadi user bisa mengetik "jun 2026", "26 jun",
     "2026-06", atau "26/06/2026" tanpa harus memakai date picker; date picker
     rentang tetap tersedia untuk penyaringan presisi. Placeholder diperbarui
     agar kemampuan ini terlihat.

  **Pembersihan data uji**: atas permintaan user, SELURUH data Recons uji
  dihapus (8 batch + 18 baris tiket) supaya bisa mulai pengujian dari nol.
  Dipastikan tidak ada tabel lain yang terdampak (ticket 1.343, delivery_support
  78, customer 146 — semuanya utuh) dan `customer_code` yang sempat diubah
  sementara oleh skrip uji sudah dikembalikan ke nilai asli (103=SML,
  142=SINERGI). Counter otomatis mulai lagi dari `0001`.

  **Verifikasi revisi ketiga**: `php -l` + Blade compile OK; JS 3 halaman lolos
  `node --check`; suite lama 37/37, suite revisi kedua 22/22, suite revisi
  ketiga 9/9 lolos.

- 2026-09-03 (revisi keempat) — 2 temuan user.

  1. **Nama file export masih memuat timestamp** — ternyata screenshot user
     berasal dari unduhan LAMA (`…_20260903_150023.xlsx`, diunduh 15:00:23,
     yaitu sebelum perbaikan revisi ketiga diterapkan). Kode saat ini sudah
     benar dan dibuktikan dari unduhan nyata: header respons persis
     `attachment; filename=MDRC-SML-2609-0002.xlsx` — tanpa awalan `Recons_`
     dan tanpa timestamp. Tidak ada perubahan kode untuk poin ini; hanya
     dipastikan tidak ada tempat lain yang membentuk nama file.

  2. **Close Date masih kosong — AKAR MASALAH DITEMUKAN, dan asumsi saya
     sebelumnya SALAH.** Pada revisi kedua saya menyimpulkan Menu Ticket
     memakai `ticket.end_date` (dilihat dari baris 1486 `endDateStr`). Yang
     benar: nilainya berasal dari `closedAtRaw` di
     [ticket/index.blade.php:1464](../../resources/views/ticket/index.blade.php#L1464),
     yang merupakan **rantai fallback tiga tingkat**:

     ```js
     ticket.end_date || (status === 'closed' ? (sla.resolved_at || updated_at) : null)
     ```

     Ini cocok dengan data: dari 1.113 tiket closed hanya **1** yang punya
     `ticket.end_date`, sementara **621** punya `ticket_sla.resolved_at`.
     Karena Recons hanya membaca tingkat pertama, kolomnya nyaris selalu
     kosong padahal Menu Ticket menampilkannya.

     Perbaikan: logika tersebut **disalin apa adanya ke sisi server** Recons
     (helper `closeDateOf()`), dan ketiga query tiket (`tickets()`,
     `eligibleTicketQuery()`, `reconsTicketRows()`) kini `leftJoin ticket_sla`
     serta ikut mengambil `updated_at`. Dicek lebih dulu bahwa
     `ticket_sla.ticket_id` **UNIQUE**, sehingga join bersifat 1:1 dan tidak
     menggandakan baris tiket. Menu Ticket sendiri **tidak disentuh** — logika
     disalin, bukan dipindah, supaya halaman yang sudah live tidak berubah.

     **Bukti paritas**: dibuat skrip pembanding yang menjalankan logika Menu
     Ticket di PHP lalu membandingkannya tiket-per-tiket dengan keluaran
     controller Recons — **487 tiket lintas 61 delivery support, 0 selisih**
     (297 di antaranya punya Close Date). Excel juga sudah menampilkan Close
     Date (mis. 28 Jul 2026 / 24 Jul 2026 / 03 Aug 2026).

  **Verifikasi revisi keempat**: `php -l` OK; suite 37/37 + 22/22 + 9/9 lolos
  (dua asersi lama di suite kedua yang masih mengunci Close Date ke `end_date`
  saja diperbarui mengikuti perilaku baru); paritas Close Date 0 selisih;
  `git status` memastikan **tidak ada satu pun file Ticket/SLA yang tersentuh**.

- 2026-09-03 (revisi kelima) — user melapor: di New Recons support #73 hanya
  muncul 12 tiket padahal di tab Recons ada >20 tiket berstatus closed.

  **AKAR MASALAH: "Customer MD" mengambil kolom yang salah — pola kesalahan
  yang sama persis dengan Close Date.** Recons memakai `ticket.man_days`,
  padahal definisi kanonik "Customer MD" di aplikasi ini adalah **total
  `customer_mandays.total_mandays` yang berstatus `approved`**:

  ```php
  // TicketController ~baris 559 & 790 (dipakai 4 tempat)
  CustomerMandays::whereIn('ticket_id', $ids)->where('status','approved')
      ->groupBy('ticket_id')->map(fn($g) => $g->sum('total_mandays'));
  ```

  dan itulah yang dirender Menu Ticket
  ([ticket/index.blade.php:1556](../../resources/views/ticket/index.blade.php#L1556)
  → `ticket.customer_mandays`). `ticket.man_days` adalah hal berbeda: bisa
  berisi **placeholder headcount** (lihat `Ticket::refreshPlaceholderManDays()`
  — 1 MD per orang, diisi agar progress tidak tampil "-") dan pada data nyata
  sering NULL walau customer sudah menyetujui mandays-nya.

  Bukti pada support #73 (39 tiket, 26 closed): 12 tiket closed punya
  `ticket.man_days` NULL — **6 di antaranya ternyata punya proposal
  `customer_mandays` berstatus approved** (1.00–2.00 MD) sehingga seharusnya
  bisa direkonsiliasi, tetapi tidak pernah muncul.

  **Perbaikan**: dibuat helper `customerMdSubquery()` (SUM approved per tiket)
  yang dipakai konsisten di seluruh alur — `tickets()` (kolom Customer MD di
  tab), `eligibleTicketQuery()` (syarat "ada Customer MD"), dan `syncLines()`
  (nilai `man_days_snapshot` yang dibekukan saat tiket masuk batch). Kunci JSON
  ke frontend tetap `man_days` supaya JS ketiga halaman tidak perlu diubah,
  tetapi isinya kini Customer MD. `ticket.man_days` **tidak dipakai lagi** di
  section Recons.

  **Dampak yang perlu disadari** (bukan bug, konsekuensi memakai definisi yang
  benar): daftar eligible support #73 tetap 12 baris tetapi **isinya berubah** —
  6 tiket MASUK (punya MD approved: 8000003195, 8000003421, 8000003482,
  8000003548, 8000003680, 8000003794) dan 6 tiket KELUAR (punya
  `ticket.man_days` tetapi `mandays_proposal_status = none`, yaitu 8000003551,
  8000003689, 8000003807, 8000004034, 8000004179, 9000000011). Beberapa support
  jadi kosong sama sekali, mis. support #8 (AIRNAV): 17 tiket closed, **0**
  yang punya Customer MD approved. Secara bisnis ini benar — tanpa MD yang
  disetujui customer tidak ada dasar untuk direkonsiliasi/ditagihkan.

  **Bukti paritas**: skrip pembanding membangun peta acuan PERSIS seperti
  `TicketController` lalu mencocokkannya tiket-per-tiket dengan keluaran
  Recons — **487 tiket lintas 61 delivery support, 0 selisih** (185 punya
  Customer MD), dan **0 tiket eligible yang melanggar syarat** (closed + MD>0).

- 2026-09-03 (revisi keenam) — **BUG: draft yang salah satu tiketnya berubah
  jadi mustahil disimpan.** User melapor: setelah submit lalu cancel, membuka
  Edit draft dan menyimpan selalu gagal dengan pesan "These tickets are no
  longer eligible … 26070105, 26070106", padahal kedua tiket itu tidak terlihat
  di layar.

  **Akar masalah (cacat desain, bukan sekadar data)**: form pre-select seluruh
  baris draft (`selectedTicketIds`), tetapi tabelnya hanya merender tiket yang
  lolos filter eligible. Baris draft yang TIDAK lagi eligible karena itu:
  ikut terkirim saat simpan, tetapi tidak pernah tampil — user tak bisa
  melihat, apalagi melepasnya. Akibatnya draft terkunci permanen. Pada kasus
  user, 26070105 & 26070106 punya `customer_mandays` approved = 0 (keduanya
  ditambahkan sebelum aturan Customer MD diperbaiki di revisi kelima), tapi
  gejala yang sama akan muncul pada sebab lain: tiket di-reopen, MD dicabut,
  atau aturan berubah lagi di kemudian hari.

  **Perbaikan (3 lapis)**:
  1. `eligibleTickets()` sekarang **selalu menyertakan seluruh baris batch yang
     sedang diedit**, walau sudah tidak eligible. Baris tersebut diberi penanda
     `in_recons: true` dan `eligible_now: false`, dan MD-nya memakai **snapshot**
     batch (bukan nilai terkini) supaya Total MD di layar sama dengan yang
     tersimpan.
  2. `assertTicketsEligible()` kini menerima tiket yang **sudah** menjadi baris
     batch ini — pemeriksaan ketat hanya berlaku untuk tiket yang BARU
     ditambahkan. Tiket yang sudah terlanjur tercatat boleh dipertahankan atau
     dilepas, tapi begitu dilepas tidak bisa ditambahkan lagi selama belum
     memenuhi syarat.
  3. Form memangkas `selected` ke id yang benar-benar ada di daftar (jaring
     pengaman), dan menampilkan badge amber "Already in this recons — no longer
     eligible" agar user paham kenapa baris itu istimewa.

  **Verifikasi — uji siklus penuh (32 asersi, semua lolos)** meniru alur nyata:
  pilih → save draft → edit (tambah & kurangi) → submit → cancel → edit lagi →
  submit ulang → export → hapus. Skenario bug direproduksi dengan **mencabut
  Customer MD** salah satu tiket di draft, lalu dipastikan: tiketnya tetap
  tampil & bertanda, draft tetap bisa disimpan (200, bukan 422), tiket bisa
  dilepas, setelah dilepas hilang dari daftar, dan menambahkannya kembali tetap
  ditolak 422. Status `customer_mandays` yang diubah sementara dipulihkan, dan
  pemulihannya dibuktikan oleh asersi penutup (jumlah eligible kembali persis
  51 seperti kondisi awal).

  **Pembersihan**: atas permintaan user seluruh data Recons dihapus (3 batch +
  11 baris tiket) supaya pengujian bisa dimulai dari nol. Tabel lain tidak
  tersentuh (ticket 1.343, delivery_support 78, customer_mandays 448,
  customer 146).

- 2026-09-03 (revisi ketujuh) — dua keluhan pada form New/Edit Recons:
  pencarian "jul" terasa tidak menyaring, dan dropdown bulan ("All months")
  tidak bisa ditekan. **Keduanya bug di kode saya sendiri, bukan di komponen
  bersama.**

  1. **Dropdown bulan tidak bisa dibuka — akar masalah: listener ganda.**
     `buildPeriodOptions()` memanggil `dd._ddInited = false;
     initCustomDropdowns(...)` setiap kali opsi dibangun ulang, sehingga tombol
     dropdown mendapat listener klik KEDUA. Pada satu klik yang sama: handler
     pertama membuka panel, handler kedua melihat panel sudah terbuka lalu
     menutupnya lagi — panel seolah "tidak bisa ditekan".
     Re-init itu memang tidak perlu: `custom-dropdown.js` menangani klik item
     lewat **event delegation di level panel**
     ([custom-dropdown.js:142](../../public/js/custom-dropdown.js#L142)) dan
     search-nya membaca `.custom-dd-item` **live** setiap ketikan
     ([baris ~334](../../public/js/custom-dropdown.js#L334)) — jadi item yang
     disisipkan belakangan sudah otomatis berfungsi.
     Perbaikan: hapus re-init, cukup segarkan tanda centang + label lewat
     `_syncMultiVisualState()`. Panel juga kini dicari lewat `dd._ddPanel`
     (ref tersimpan) agar tetap ketemu saat panel sedang dilepas ke `<body>`
     dalam mode fixed. **`custom-dropdown.js` sendiri TIDAK disentuh.**

  2. **Pencarian tanggal terasa tidak menyaring.** Sebelumnya kata kunci
     dicocokkan ke Start Date DAN Close Date sekaligus. Karena mayoritas tiket
     pada satu support ditutup di bulan yang sama, mengetik "jul" ikut
     menjaring tiket yang Start Date-nya Juni — hasilnya terlihat seperti tidak
     tersaring. Perbaikan: pencocokan tanggal kini **mengikuti dasar tanggal
     yang sedang dipilih** (Start Date / Close Date), konsisten dengan kontrol
     "Filter by date" tepat di bawahnya. Placeholder kotak pencarian ikut
     menyebut dasar aktif ("…or start date (e.g. Jul 2026)") supaya jelas.
     Pencarian nomor tiket & deskripsi tidak berubah.

  **Verifikasi**: dibuat uji JS (`recons_search_test.js`, 20 asersi) yang
  **mengekstrak fungsi `matchesTerm` dan `buildPeriodOptions` langsung dari file
  Blade** lalu menjalankannya di Node — jadi yang diuji benar-benar kode yang
  dikirim ke browser, bukan salinan. Dibuktikan: basis Start Date → "jun"
  cocok & "jul" TIDAK cocok (inti keluhan user); basis Close Date → kebalikannya;
  format "2026-07" dan "01/07/2026" ikut cocok; pencarian nomor tiket/deskripsi
  tetap jalan; tiket tanpa close date tidak menimbulkan error; dan
  `buildPeriodOptions` dipastikan tidak lagi memanggil `initCustomDropdowns`
  maupun menyetel `_ddInited = false`.

  **Catatan data**: 3 recons yang dibuat user lewat browser (17:27–17:30)
  menahan seluruh 14 tiket eligible support #57, sehingga support itu kini
  menampilkan 0 tiket eligible — perilaku benar, bukan bug. Suite uji yang
  semula mem-*hardcode* support #57/#8 diubah agar **memilih support secara
  dinamis** (yang benar-benar punya tiket eligible saat itu) supaya tidak rapuh
  terhadap data uji yang berubah-ubah.

- 2026-09-03 (revisi kedelapan) — user melapor memilih bulan "Jul 2025" tidak
  mengubah tabel, dan mengusulkan tombol Apply/Filter di samping Reset filter.

  **Akar masalah: `data-onchange` TIDAK PERNAH terpanggil.** custom-dropdown.js
  memanggil callback-nya lewat `window[onchangeFn]()`
  ([baris 155](../../public/js/custom-dropdown.js#L155)) — sebuah lookup
  **datar** yang tidak mengurai nama bertitik. Form ini menulis
  `data-onchange="ReconsForm.render"`, sehingga yang dicari adalah
  `window["ReconsForm.render"]` → `undefined`, dan callback-nya dilewati
  diam-diam. Akibatnya **dua kontrol sekaligus mati**: pilihan bulan dan
  pemilih dasar tanggal (Start/Close Date) tidak pernah menerapkan apa pun.
  Seluruh halaman lain memang memakai nama fungsi global polos
  (`applyFilters`, `applyColFilter`, dst) — konvensi itu yang saya langgar.

  **Perbaikan**:
  - Ditambahkan dua fungsi global polos `window.reconsApplyDateFilter` dan
    `window.reconsChangeDateBasis` sebagai jembatan ke `ReconsForm`, lalu
    `data-onchange` diarahkan ke keduanya. Filter kini menerapkan otomatis
    begitu pilihan berubah. **`custom-dropdown.js` tidak disentuh.**
  - Ditambahkan tombol **"Apply filter"** di samping "Reset filter" sesuai
    permintaan user. Selain memberi kendali eksplisit, tombol ini berguna untuk
    input rentang tanggal yang tidak selalu memicu event `change`.
  - `applyDateFilter()` menolak rentang terbalik (tanggal "to" lebih awal dari
    "from") dengan toast, bukan diam-diam menampilkan tabel kosong.

  **Verifikasi**: uji JS diperluas menjadi 32 asersi — termasuk memastikan
  custom-dropdown.js memang memakai lookup datar, **tidak ada satu pun
  `data-onchange` bertitik di SELURUH view aplikasi** (dicek menyeluruh, bukan
  hanya file ini), setiap nama pada `data-onchange` benar-benar terdefinisi
  sebagai fungsi global di halaman ter-render, fungsi yang dirujuk diekspor
  oleh `ReconsForm`, serta tombol Apply/Reset ada dan memanggil fungsi yang
  benar. Regresi penuh tetap hijau (36+22+7+32+18 PHP + 32 JS).

- 2026-09-03 (revisi kesembilan) — **Recons Date & Description dijadikan wajib
  diisi** (permintaan user). Perubahan sengaja dibuat sesempit mungkin: hanya
  2 file (form Blade + controller Recons), murni penambahan, tidak menyentuh
  alur lain yang sudah lolos uji user.

  - **Penanda**: label diberi `<span class="text-red-500">*</span>`, mengikuti
    konvensi yang sudah dipakai form Delivery Support lain
    (`list/create.blade.php`, modal di `list/show.blade.php`).
  - **Semantik/aksesibilitas**: input diberi `required` + `aria-required="true"`.
  - **Umpan balik di layar**: saat kosong → border merah + ring, pesan inline di
    bawah field ("Recons date is required." / "Description is required."), toast
    error menyebut field mana saja yang kurang, dan kursor otomatis diarahkan ke
    field kosong pertama. Tanda merah langsung hilang begitu field diisi
    (`oninput`/`onchange` → `clearFieldError`).
  - **Urutan validasi**: header divalidasi lebih dulu, baru pengecekan tiket —
    percuma memeriksa tiket kalau data wajibnya belum lengkap.
  - **Penjaga terakhir di server**: `validatePayload()` kini `required` untuk
    keduanya (sebelumnya `nullable`, dan `recons_date` diam-diam diisi hari ini
    kalau kosong). Description disimpan ter-`trim`.

  **Verifikasi (74 asersi baru, semua lolos)**:
  - Sisi server (20): kosong/tidak dikirim/hanya spasi/bukan tanggal → 422
    dengan pesan yang tepat; lengkap → 201; description tersimpan ter-trim;
    aturan lama tidak berubah (tanpa tiket tetap 422, guard support lain tetap
    404 karena dicek sebelum validasi, batch submitted tetap terkunci); tidak
    ada batch yang terlanjur dibuat dari percobaan yang gagal.
  - Sisi klien (22): fungsi `validateRequiredFields`, `setFieldError`, dan
    `clearFieldError` **diekstrak langsung dari file Blade** lalu dijalankan di
    Node dengan DOM tiruan — dibuktikan menandai field yang benar saja,
    menampilkan pesan inline, memfokuskan field kosong pertama, menolak
    deskripsi berisi spasi saja, lolos saat keduanya terisi, dan dipanggil
    sebelum pengecekan tiket di `save()`.
  - Regresi penuh tetap hijau: 36+22+7+32+20+18 (PHP) + 54 (JS), plus kedua uji
    paritas Close Date & Customer MD.

- 2026-09-03 (revisi kesepuluh) — **BUG: toast sukses tidak muncul pada aksi
  Recons yang diikuti pindah/reload halaman.** User melapor "Add New Recons →
  Submit tidak ada toast success".

  **Akar masalah**: `notify(msg,'success')` dipanggil, lalu di baris yang sama
  `window.location.href = redirect_url` / `window.location.reload()`. `showToast()`
  ([dashboard.blade.php:1724](../../resources/views/dashboard.blade.php#L1724))
  baru menampilkan toast setelah 2 `requestAnimationFrame` (~32 ms), sedangkan
  navigasi mulai seketika sehingga DOM (termasuk `#toast-container`) keburu
  dibuang. Halaman tujuan juga tidak menampilkan apa pun karena redirect
  dilakukan client-side, bukan `redirect()->with('success')`, jadi tidak ada
  `session('success')`.

  **Cakupan** (dikonfirmasi lewat telaah kode):
  - **Kena** — `ReconsForm.save('draft'|'submit')` (form New & Edit),
    `ReconsDetail.submit()`, `ReconsDetail.cancel()` (halaman detail).
  - **Tidak kena** — `SupportRecons.askCancel()` / `askDelete()` di tab Recons
    List: sudah refresh AJAX in-place tanpa reload, toast tampil normal. Semua
    jalur error juga aman (tidak ada navigasi di blok `catch`).

  **Perbaikan (Opsi A — pola flash `session('success')` yang sama dipakai
  `DeliverySupportController::store()`), disetujui user:**
  - Controller `store()` & `update()`: `session()->flash('success', $message)`
    sebelum mengembalikan JSON. Form tetap `window.location.href = redirect_url`
    (tanpa lagi memanggil `notify` sukses); toast dirender di halaman detail
    Recons oleh blok `session('success')` di `dashboard.blade.php`. Perilaku
    identik dengan alur "New Delivery Support".
  - Controller `submit()` & `cancel()`: flash hanya bila request memuat
    `redirect: true`. Halaman detail Recons mengirim flag itu di body fetch lalu
    `location.reload()` — toast tampil sesudah reload, user tetap di halaman itu.
    Tab Recons List **tidak** mengirim flag → tetap memakai toast JS in-place
    (mencegah flash "nyangkut" dan muncul di navigasi berikutnya).
  - `recons-scripts.blade.php` (tab Recons List) **tidak disentuh**.

  **File**: `DeliverySupportReconsController.php`, `recons/form.blade.php`,
  `recons/show.blade.php` (3 file, +50/−13). `php -l` OK; Blade `compileString()`
  + `php -l` untuk 3 view OK.

- 2026-09-03 (tambahan, DI LUAR modul Recons) — **filter + search kolom
  "Assign Delivery" pada Menu Ticket** (halaman produksi). User menyetujui
  bahwa izin Recons untuk role lain cukup diatur lewat Menu Management, dan
  duplikat master customer dibiarkan dulu karena bukan perubahan dari kita.

  Dikerjakan mengikuti pola kolom **Type** yang sudah ada, tanpa membuat
  komponen baru:
  - **Header** `<th>` polos diganti dropdown `.custom-dd` multi-select
    (`data-fixed`, `data-multi`, `data-onchange="applyColFilter"`) — identik
    dengan Type/Module. Ditambah `data-searchable="true"` **eksplisit** karena
    opsinya diisi belakangan lewat AJAX, sedangkan ambang auto-inject search di
    `custom-dropdown.js` dihitung saat init (panel masih kosong) — pelajaran
    dari kasus filter Module di Master Employee.
  - **Opsi** diambil dari `/api/tickets/filter-options` yang kini juga
    mengembalikan `deliveries` (hanya Delivery Support yang benar-benar punya
    tiket; label `"<nama> (<customer>)"`, sama seperti isi kolomnya). Diisi
    oleh `populateDeliveryFilter()` yang mencerminkan
    `populateCustomerFilter()`, tetapi memakai markup item multi-select
    (`.custom-dd-item-text` + `.custom-dd-check`) dan `textContent` (aman dari
    HTML injection).
  - **Backend**: satu blok baru di `applyTicketListFilters()` — helper yang
    sudah dipakai bersama oleh listing, my-tickets, dan export, sehingga filter
    otomatis konsisten di ketiganya. Memakai `whereExists` (bukan join) supaya
    tiket dengan beberapa activity pada support yang sama tidak menghasilkan
    baris ganda. Mendukung multi-select CSV dan opsi **"Unassigned"**
    (`__unassigned__`, mengikuti konvensi filter PIC).
  - Filter baru diikutkan ke seluruh siklus yang sudah ada: parameter
    `loadTickets()` & `exportWithFilters()`, indikator aktif `applyColFilter()`,
    simpan/pulihkan state di sessionStorage, dan `resetFilters()`.

  **Verifikasi**: 18 uji khusus lolos — jumlah hasil filter dicocokkan dengan
  hitungan langsung ke tabel (1 delivery = 39, 2 delivery = 88, Unassigned =
  851, gabungan = 890, tanpa filter = 1.324), tanpa baris ganda, filter lama
  (Type) tetap berfungsi dan berkombinasi secara AND, parameter kosong/koma
  tidak menyaring apa pun, serta input tak wajar (percobaan SQL injection)
  ditangani lewat binding dan tabel `ticket` tetap utuh 1.343 baris.
  Endpoint `/api/tickets` diuji end-to-end (total 39/88/851/1324 sesuai).
  Blade compile OK, seluruh 13 blok script hasil render lolos `node --check`,
  halaman ter-render 378 KB dengan semua filter lama (Customer/PIC/Priority/
  Scale/Status/Type/Module) tetap ada. `git diff` menunjukkan satu-satunya
  penghapusan adalah `<th>` lama yang digantikan versinya yang berfilter
  (jumlah kolom tidak berubah) — `custom-dropdown.js` yang dipakai 40+ halaman
  lain TIDAK disentuh.

  **Verifikasi revisi kelima**: `php -l` OK; suite regresi ditulis ulang agar
  seluruh angka harapan DIHITUNG dari data (tidak ada angka mati) dan diarahkan
  ke support #40 yang punya data eligible — 36/36 + 22/22 + 9/9 lolos; paritas
  Close Date & Customer MD dua-duanya 0 selisih; JS 3 halaman lolos
  `node --check`; `git diff --stat` menunjukkan hanya **4 file lama** yang
  disentuh dan semuanya **murni penambahan** (80 insertions, 0 deletions) —
  tidak ada file Ticket/SLA/Customer yang berubah.

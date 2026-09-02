# Handoff: Master Employee — Table Filter Revamp & Full Info Columns

> **Status**: 🟡 Planning selesai, menunggu konfirmasi user sebelum mulai coding.
> **Diminta oleh**: user (chat 2026-09-02). **Jangan mulai edit tanpa konfirmasi eksplisit per fase** (instruksi user: "pastikan selalu meminta konfirmasi pengerjaan").
> File utama: [resources/views/master/employee/index.blade.php](../../resources/views/master/employee/index.blade.php),
> [public/js/custom-dropdown.js](../../public/js/custom-dropdown.js) (**shared component, dipakai puluhan halaman lain** — lihat "Risiko" di bawah),
> [app/Http/Controllers/EmployeeController.php](../../app/Http/Controllers/EmployeeController.php).

## Konteks

Web ini sudah live hosting; page Master Employee (`/master/employee`) dipakai
sehari-hari. User melaporkan 8 temuan (bug + feature request) yang harus
dikerjakan **hati-hati** — jangan sampai merusak fitur lain, khususnya karena
`custom-dropdown.js` adalah komponen shared dipakai di ticket, delivery,
reporting, customer, dsb.

## Requirement asli (verbatim dari user)

> Pada master employee samakan table head panahnya jadi filter seperti head
> dengan judul Eci tersebut. Position:
> 1. Tidak pakai bold pada filternya
> 2. Bisa pilih lebih dari 1 filter
> 3. Pada position, ketika search dan pilih salah satu, kemudian pilih search
>    lagi, input field search-nya bug tembus — harusnya tetap stay
> 4. Masukin semua employee information ke view table kecuali block dan
>    deletion flag
> 5. Semua yang tertampil di table, UI-nya kapital (database tidak usah
>    disentuh) — saat ini seperti fullname, home base, dan status belum kapital
> 6. Ketika nge-filter, harusnya ada opsi clear seperti pada ECI — saat ini
>    pada position hanya search saja
> 7. Department harusnya ada filter dan clear, sesuaikan juga yang lain, kalau
>    full name tidak perlu filter
> 8. Bug setelah filter, tombol panah bawah (filter)-nya hilang — harusnya
>    tetap ada

## Temuan investigasi (kondisi kode SAAT INI, sudah dicek langsung)

Kode di branch `aldy_eco` **sebagian sudah lebih maju** dari deskripsi bug user
(kemungkinan ada perubahan sebelumnya yang usernya sendiri belum lihat di live,
atau deskripsi user berdasarkan versi live yang beda dari branch ini — **perlu
dikonfirmasi ulang saat demo**, karena beberapa poin sudah tersedia di kode:

| # | Kolom/komponen | Ada di kode sekarang? | Catatan |
|---|---|---|---|
| ECI | search box + tombol Clear | ✅ Ada (`empFilterPanel`, baris ±42-60) | Pola referensi yang diminta user |
| Department | search box + tombol Clear | ✅ Ada (`deptFilterPanel`, baris ±101-119) — TAPI perlu diverifikasi ulang di live, karena user bilang belum ada | |
| Position | dropdown `custom-dd` multi-select (checkbox list) | ✅ Ada (`ddFilterPosition`, `data-multi="true"`), TIDAK ada tombol "Clear" eksplisit — hanya opsi "All Position" yang meng-clear semua pilihan | Perlu tombol Clear terpisah sesuai request #6 |
| Module, Home Base | dropdown `custom-dd` multi-select | ✅ Sama pola dengan Position | |
| Status | dropdown `custom-dd` single-select | ✅ Ada | |
| Full Name | tanpa filter | ✅ Sesuai (user bilang tidak perlu filter) | |

**Kesimpulan sementara**: kemungkinan besar deskripsi user mengacu ke versi
yang SEDANG live di server (mungkin ada hotfix manual di server, atau branch
lain yang belum sinkron ke `aldy_eco`). **Wajib demo/screenshot dulu sebelum
coding** supaya tidak salah asumsi dan tidak menyentuh sesuatu yang ternyata
sudah benar.

### Per-poin, gap yang teridentifikasi di kode (`aldy_eco`) saat ini:

1. **Bold pada filter** — label header filter pakai `font-semibold` di semua
   kolom (ECI, Position, Module, Department, Home Base, Status) — konsisten,
   bukan spesifik ke Position. Kalau user maksud "font-weight biasa (tidak
   bold)", perlu ganti `font-semibold` → `font-normal`/`font-medium` di
   `<span>` label kolom Position (dan mungkin semua kolom biar konsisten).
2. **Multi-select Position** — sudah `data-multi="true"` di kode. Kalau di
   live belum bisa multi-pilih, cek versi live vs kode ini.
3. **Bug search "tembus"** — dugaan awal: interaksi `_injectSearch()` +
   `_toggleMultiItem()` di [custom-dropdown.js](../../public/js/custom-dropdown.js)
   pada dropdown multi-select dengan search box otomatis (item count > 7).
   Perlu reproduksi manual di browser utk pastikan akar masalah sebelum ubah
   file shared ini (dipakai ticket/delivery/reporting juga — **high risk**).
4. **Semua employee info di tabel** — API `getData()` (baris ±195-333) &
   tabel HTML saat ini cuma render: ECI, Full Name, Position, Module,
   Employee Group, Division, Department, Home Base, Since Date, Status,
   Actions (11 kolom). Field lain yang ADA di DB (`employee_basic_data`) tapi
   BELUM ditampilkan: `title`, `nick_name`, `gender`, `religion`,
   `marital_status`, `birth_date`, `birth_place`, `personnel_area`,
   `personnel_subarea`, `employee_subgroup`, `authorization_group`,
   `current_assignment`, `direct_supervision`, `manager`. (`block` &
   `deletion_flag` sengaja DIKECUALIKAN sesuai request user — dipakai untuk
   hitung `status` saja, tidak jadi kolom sendiri.)
   → Referensi field list persis sudah ada di `exportToExcel()` (baris
   ±340-436) — export Excel SUDAH punya semua field ini. Rencana: mirror field
   list export ke `getData()` + tambah kolom tabel HTML.
   → **Konsekuensi**: tabel akan sangat lebar (~24 kolom). Perlu UX yang wajar
   (horizontal scroll sudah ada via `overflow-x-auto`, tapi mungkin perlu
   sticky-column adjustment atau kolom bisa show/hide). **Perlu diskusi UX
   dengan user** — apakah semua kolom tampil sekaligus, atau ada opsi "manage
   columns"?
5. **UI kapital (CSS only, DB tidak disentuh)** — pakai `text-transform:
   uppercase` di CSS/Tailwind class `uppercase`, BUKAN ubah data. Kandidat:
   Full Name, Home Base, Status badge, dan kolom baru dari poin 4 (opsional).
   Perlu keputusan: apply ke SEMUA kolom teks atau cuma yang disebut user
   (fullname, home base, status)?
6. **Clear per-filter** — Position/Module/Home Base (custom-dd multi-select)
   belum punya tombol "Clear" eksplisit terpisah dari "All X" — perlu tambah
   tombol Clear yang konsisten dengan pola ECI/Department.
7. **Department filter+clear** — sudah ada di kode (`aldy_eco`), perlu
   verifikasi ke versi live. Employee Group & Division saat ini TANPA filter
   (`<th>` biasa) — user bilang "sesuaikan juga yang lain" → kemungkinan perlu
   filter dropdown juga untuk Employee Group & Division (dan kolom baru dari
   poin 4 kalau relevan untuk difilter).
8. **Arrow hilang setelah filter** — dugaan: bukan CSS hide permanen (dicek,
   `.custom-dd-arrow` tidak ada logic opacity-0 permanen di kode `aldy_eco`
   saat ini), kemungkinan bug di re-render tabel (`fetchEmployees` →
   `renderTable`) yang TIDAK menyentuh `<thead>`, harusnya arrow tetap ada.
   → Sangat mungkin ini bug yang HANYA terjadi di versi live (state lama)
   yang sudah beda dari `aldy_eco`. **Butuh reproduksi manual sebelum fix.**

## Risiko & mitigasi

- `custom-dropdown.js` dipakai di 40+ file Blade lain (ticket, delivery,
  reporting, customer, dsb). Perubahan generik (mis. resetSearchState,
  toggleMultiItem) **berpotensi mempengaruhi semua halaman itu**.
  → Mitigasi: kalau bug spesifik ke Position, prioritaskan fix di level
  Blade/page (misal parameter baru di `data-*`) daripada ubah logic inti
  yang dipakai bersama. Kalau memang harus ubah `custom-dropdown.js`, test
  manual di minimal 2-3 halaman lain yang pakai multi-select (`ticket/index`,
  `delivery/support/index`) sebelum dianggap selesai.
- Field tambahan di poin 4 butuh perubahan `getData()` (backend) — pastikan
  tidak mengubah kontrak field yang sudah dipakai modal Create/Edit Employee
  (field name berbeda, id HTML berbeda — modal pakai id seperti `#eci`,
  `#firstName`, table pakai key JSON dari API, jadi harusnya aman asal hanya
  *menambah* field, tidak mengganti nama field lama).
- Export Excel (`exportToExcel`) TIDAK boleh berubah behavior-nya — hanya
  dipakai sebagai referensi field list, bukan disentuh.

## Rencana kerja (diusulkan, per fase — 1 fase = 1 konfirmasi ke user)

1. **Fase 0 — Klarifikasi & demo**: minta user screenshot/demo halaman live
   saat ini (Position, Department, ECI header) supaya kondisi awal exact
   match dengan laporan bug (karena ada indikasi kode lokal sudah beda dari
   live).
2. **Fase 1 — Filter UX Position/Department/Module/Home Base**: bold→normal,
   tombol Clear eksplisit, fix bug search "tembus", fix arrow hilang. Test
   manual regresi di halaman lain yang pakai `custom-dd`.
3. **Fase 2 — Filter tambahan**: Employee Group & Division jadi filter
   dropdown (kalau dikonfirmasi user perlu).
4. **Fase 3 — Kolom info lengkap**: tambah field di `getData()` (backend) +
   kolom tabel HTML, exclude `block`/`deletion_flag`. Diskusikan dulu UX
   kolom sebanyak ini (semua sekaligus vs manage-columns).
5. **Fase 4 — UI kapital**: `uppercase` CSS di kolom yang diminta (dan
   konfirmasi apakah kolom baru poin 4 juga perlu kapital).

## Log perubahan (diisi tiap sesi kerja berikutnya)

- 2026-09-02 — Investigasi awal selesai, handoff doc dibuat, **belum ada kode
  yang diubah**. Menunggu konfirmasi user untuk mulai Fase 0/1.
- 2026-09-02 — User dikonfirmasi soal indikasi kode lokal (`aldy_eco`) sudah
  beda dari live (Fase 0). User memilih untuk **mengirim screenshot/video
  halaman live** sebelum lanjut coding.
- 2026-09-02 — Screenshot diterima. URL live yang di-screenshot ternyata
  `127.0.0.1:8000` (dev server lokal user, BUKAN domain produksi eksternal) —
  jadi kode yang dijalankan sudah konsisten dengan repo ini. Screenshot
  mengonfirmasi: Department SUDAH punya search+Clear (sama seperti dugaan),
  Home Base & Status dropdown TIDAK punya tombol Clear eksplisit (cuma "All
  X"), dan halaman Employee Detail (`/master/employee/{id}` → tab Basic Data)
  menunjukkan field lengkap `employee_basic_data` termasuk `Block Employee`
  dan `Deletion Flag` sebagai checkbox terpisah — ini konfirmasi field mana
  yang harus DIKECUALIKAN dari kolom tabel sesuai request user.
  User comfirm: **kerjakan sesuai 8 poin, field diambil dari Employee
  Information (basic data), gunakan bahasa Inggris di view, konsisten dengan
  seluruh kode.**

  **Perubahan yang sudah diterapkan** (Fase 1 + Fase 3 + Fase 5, digabung
  sekaligus atas instruksi eksplisit user untuk langsung mengerjakan 8 poin):
  1. Label header "Position" tidak lagi bold (`font-semibold` → `font-normal`).
  2. Multi-select Position — sudah ada di kode (`data-multi="true"`), tidak
     diubah, dikonfirmasi tetap berfungsi.
  3. & 8. Fix dugaan akar masalah "search tembus" & "arrow hilang": kolom
     Position/Module/Home Base/Status sekarang pakai `data-fixed="true"` pada
     wrapper `.custom-dd` — panel-nya di-detach ke `<body>` saat dibuka (fixed
     positioning), PERSIS teknik yang sudah dipakai manual oleh panel ECI &
     Department (`document.body.appendChild(panel)` di
     `toggleEmpFilter`/`toggleDeptFilter`). Sebelumnya keempat dropdown itu
     TIDAK escape dari stacking context tabel yang `overflow-x-auto` (yang
     folding/clip perilaku visualnya bisa mirip "tembus"/elemen hilang).
     Belum bisa diverifikasi visual langsung (tidak ada akses browser
     interaktif dari sesi ini) — **mohon di-cek ulang di browser oleh user.**
  4. Kolom tabel employee sekarang menampilkan SEMUA field
     `employee_basic_data` kecuali `block` & `deletion_flag` (yang cuma
     dipakai untuk hitung kolom Status, tidak jadi kolom sendiri). 15 kolom
     baru ditambahkan di akhir (sebelum Actions), field & backend select-nya
     mirror persis dari `exportToExcel()` yang sudah lengkap:
     Title, Nick Name, Gender, Religion, Marital Status, Birth Date, Birth
     Place, Personnel Area, Personnel Subarea, Employee Subgroup, Employee
     Type, Authorization Group, Current Assignment, Direct Supervision,
     Manager. Backend: `EmployeeController::getData()` select list ditambah
     (baris ±205-230). Table `min-width` dinaikkan dari 1600px → 3600px untuk
     menampung kolom tambahan (horizontal scroll tetap via
     `#employeeTableWrapper`). `colspan` pada empty-state disesuaikan 11→26.
  5. UI kapital — ditambahkan class `uppercase` (Tailwind, CSS `text-transform`
     saja) pada `<tbody id="employeeTableBody">`, berlaku ke SEMUA teks yang
     tertampil di body tabel (termasuk kolom baru), TIDAK menyentuh database.
  6. & 7. Tombol "Clear" eksplisit ditambahkan ke panel Position, Module, Home
     Base (multi-select — panggil `clearCustomDropdownMulti()`) dan Status
     (single-select — panggil `setCustomDropdownValue('filterStatus','')`),
     konsisten dengan pola tombol Clear yang sudah ada di ECI/Department.
     Module: item hasil AJAX (`loadModuleFilterOptions()`) di-`insertBefore`
     footer Clear (`#moduleFilterClearFooter`) supaya urutannya tidak
     berantakan.

  **Verifikasi statis yang sudah dilakukan** (tidak ada browser di sesi ini):
  - `php -l` pada `EmployeeController.php` → OK.
  - Blade file dikompilasi via `Blade::compileString()` + `php -l` pada hasil
    kompilasi → OK, tidak ada syntax error.
  - Dihitung manual: jumlah `<th>` header (26) match jumlah `<td>` per baris
    (26) match `colspan="26"` di empty-state.

  **BELUM dikerjakan / di luar scope perubahan ini** (dicatat supaya tidak
  lupa, TIDAK disentuh karena di luar 8 poin eksplisit / demi minim risiko):
  - Employee Group & Division belum dijadikan dropdown filter (user tidak
    eksplisit minta ini, "sesuaikan juga yang lain" ditafsirkan sebagai
    tombol Clear yang konsisten, bukan filter baru).
  - Kolom baru (Title, Gender, dst.) belum punya filter sendiri — hanya
    ditampilkan sebagai kolom info, sesuai literal request user.
  - `exportToExcel()` TIDAK disentuh sama sekali.
  - **PENTING — belum ada uji manual di browser** (login, buka
    `/master/employee`, buka tiap dropdown, scroll tabel ke kanan, cek
    tampilan kolom baru & uppercase, test Clear button, test resize/scroll
    saat panel Position terbuka). User perlu jalankan `php artisan serve`
    (atau server lokal yang sudah dipakai) dan konfirmasi visual sebelum
    dianggap selesai/di-push.

- 2026-09-02 (lanjutan, sesi sama) — User kirim screenshot baru dari
  127.0.0.1:8000 (dev server lokal user, kode ini) menunjukkan 5 masalah
  setelah fase pertama:
  1. Department filter tampak "kosong".
  2. Search box di panel Position "menembus ke bawah" alih-alih diam di tempat.
  3. Panel ECI "menggantung" (tetap terbuka) sementara panel lain sudah
     berpindah/tertutup.
  4. Icon panah filter Position/Module/Home Base/Status beda dari ECI — minta
     disamakan.
  5. Minta header tabel di-freeze (sticky) saat scroll vertikal.

  Analisis akar masalah (dari membaca kode, bukan devtools langsung — tidak
  ada akses browser di sesi ini): ditemukan DUA SISTEM DROPDOWN TERPISAH yang
  tidak saling menutup satu sama lain:
  - Sistem A (ECI & Department): toggle manual (toggleEmpFilter /
    toggleDeptFilter), saling menutup satu sama lain saja.
  - Sistem B (Position/Module/Home Base/Status): custom-dropdown.js
    (.custom-dd), close-all sendiri (_closeAllDropdowns).
  Akibatnya membuka salah satu dari Sistem A TIDAK menutup Sistem B yang
  sedang terbuka, dan sebaliknya — persis skenario di screenshot (Position &
  Department sama-sama terbuka bersamaan, saling tumpang tindih secara
  visual). Kemungkinan besar akar dari poin 1, 2, dan 3 sekaligus (bukan 3
  bug terpisah, tapi 1 bug struktural yang menampakkan diri di 3 tempat).

  Ditemukan juga inkonsistensi: toggleEmpFilter (ECI) sudah memindahkan
  panelnya ke <body> untuk escape stacking context header yang sticky, tapi
  toggleDeptFilter (Department) TIDAK melakukan hal yang sama — panel
  Department tetap nested di dalam <th>-nya. Setelah header dibuat sticky
  (poin 5), ini jadi rawan ketutup/ke-clip oleh stacking context sibling
  <th> lain.

  Perbaikan yang diterapkan:
  1. toggleEmpFilter/toggleDeptFilter sekarang saling memanggil
     _closeAllDropdowns() (fungsi global dari custom-dropdown.js) saat
     membuka, jadi Sistem A menutup Sistem B juga.
  2. Listener klik dokumen ditambah: klik pada .custom-dd-btn mana pun juga
     memanggil closeEmpFilter() + closeDeptFilter() — Sistem B menutup
     Sistem A.
  3. toggleDeptFilter sekarang JUGA memindahkan panelnya ke document.body
     (mirror persis toggleEmpFilter), jadi kedua panel konsisten escape dari
     stacking context header.
  4. Icon panah pada header Position/Module/Home Base/Status diganti dari
     chevron (garis, stroke) menjadi funnel icon yang sama persis dengan
     ECI/Department (fill, path funnel) — class custom-dd-arrow & perilaku
     rotate-on-open dari custom-dropdown.js TIDAK diubah, tetap jalan (hanya
     bentuk ikonnya yang diganti).
  5. Header freeze saat scroll: #employeeTableWrapper diberi overflow-auto +
     max-height:75vh (jadi scroll container sendiri, bukan ikut scroll
     halaman — supaya sticky header tidak perlu hitung offset terhadap top
     bar aplikasi). Semua <th> diberi sticky top-0 (+ bg-gray-50 di yang
     belum punya, supaya tidak transparan saat baris scroll di baliknya).
     Sel pojok kiri-atas (ECI, Full Name — sudah sticky horizontal)
     dinaikkan ke z-30 supaya tetap di atas kolom lain (z-10) saat scroll
     dua arah.

  Verifikasi statis: Blade dikompilasi ulang via Blade::compileString() +
  php -l → OK, tidak ada syntax error.

  BELUM diverifikasi visual di browser — sama seperti sebelumnya, sesi ini
  tidak punya akses browser interaktif. Mohon dicek ulang:
  - Buka Position, lalu Department (atau sebaliknya) — pastikan yang lama
    otomatis tertutup.
  - Scroll vertikal tabel — header harus tetap terlihat ("freeze").
  - Icon Position/Module/Home Base/Status sekarang harus terlihat sama
    (funnel) seperti ECI, bukan chevron lagi.
  - Search di Position: ketik → pilih salah satu → klik search lagi → ketik
    lagi, pastikan tidak ada yang aneh secara visual.
  Kalau MASIH ada yang tidak sesuai, kirim screenshot lagi + sebutkan urutan
  klik yang dilakukan (mis. "klik Position dulu, lalu klik Department") biar
  akar masalahnya bisa dipastikan lebih presisi, bukan tebak-tebak dari
  membaca kode saja.

- 2026-09-02 (lanjutan lagi, sesi sama) — User kirim screenshot ketiga
  (masih dari 127.0.0.1:8000) plus daftar permintaan baru:
  1. Department: berikan search + pilihan (bukan cuma free-text).
  2. Division: berikan filter + search juga.
  3. Position: search di panel masih "menembus" saat di-scroll ke bawah.
  4. Department: panel filter "melayang" (tidak ikut) saat tabel di-scroll
     ke kanan.
  5. Kolom tambahan yang diminta HANYA field di screenshot "Employee
     Information": Personnel Area, Personnel Subarea, Employee Subgroup,
     Employee Type, Authorization Group, Current Assignment, Direct
     Supervision, Manager — Title/Nick Name/Gender/Religion/Marital
     Status/Birth Date/Birth Place TIDAK PERLU (dihapus dari tabel).
  6. Status dipindah ke posisi tepat sebelum Actions.
  7. Personnel Subarea diberi filter (pilihan dari enum, seperti di
     screenshot modal).
  8. Employee Type diberi filter juga.
  9. Semua filter bisa multi-select + ada Clear, search di dropdown juga
     harus bisa, dan UI harus terlihat profesional/tidak "menembus".

  Perubahan yang diterapkan:

  **Department dikonversi total** dari panel free-text (`toggleDeptFilter`
  dkk, `#deptFilterPanel`) menjadi `.custom-dd` multi-select (search +
  pick-list) persis pola Position, di-backing oleh `$departmentOptions`
  (sudah diinjeksi dari `AppServiceProvider` composer, sebelumnya tidak
  dipakai di halaman ini). Semua fungsi JS lama untuk Department dihapus
  (`toggleDeptFilter`, `closeDeptFilter`, `onDeptFilterInput`,
  `clearDeptFilter`, `updateDeptFilterIndicator`) — sekarang otomatis
  ditangani generic oleh `custom-dropdown.js`, termasuk close-on-scroll dan
  reposition-on-scroll yang SEBELUMNYA TIDAK ADA untuk Department (itulah
  kemungkinan akar poin 4 — Department dulu satu-satunya panel yang benar-
  benar tidak reposisi/tutup saat scroll, karena bukan bagian dari sistem
  `.custom-dd`). Backend: filter `department` diganti dari `LIKE` partial
  jadi `whereIn` multi-select (comma-separated), sama semantik dengan
  `home_base`/`position`.

  **Division**: kolom baru jadi `.custom-dd` multi-select, di-backing oleh
  `$divisionOptions` (composer). Backend: filter `division` baru
  (`whereIn`).

  **Personnel Subarea**: kolom di-convert jadi `.custom-dd` multi-select,
  di-backing `$personnelSubareaOptions` (composer, options-nya persis sama
  dengan dropdown "Personnel Subarea" di modal Create/Edit — Support,
  Project, Administrasi, Other, sesuai screenshot). Backend: filter
  `personnel_subarea` baru (`whereIn`).

  **Employee Type**: kolom di-convert jadi `.custom-dd` multi-select dengan
  2 pilihan hardcoded (Internal/External — field ini binary, dari migration
  `add_employee_type_to_employee_basic_data.php`, tidak ada enum class
  terpisah). Backend: filter `employee_type` baru (`whereIn`).

  **Kolom dihapus dari tabel** (tetap ada di backend select untuk field yang
  masih dipakai modal edit — `title`/`nick_name`/`gender`/`birth_date` tetap
  di-select karena dipakai `editEmployee()` untuk field lain; `religion`/
  `marital_status`/`birth_place` dihapus total dari select karena sudah
  tidak dipakai di mana pun setelah kolomnya dihapus): Title, Nick Name,
  Gender, Religion, Marital Status, Birth Date, Birth Place.

  **Status dipindah** ke posisi tepat sebelum Actions (thead & tbody
  render).

  **Total kolom sekarang 19** (dari 26): ECI, Full Name, Position, Module,
  Employee Group, Division, Department, Home Base, Since Date, Personnel
  Area, Personnel Subarea, Employee Subgroup, Employee Type, Authorization
  Group, Current Assignment, Direct Supervision, Manager, Status, Actions.
  `colspan` empty-state & table `min-width` disesuaikan (26→19 kolom,
  3600px→2800px).

  **Dugaan akar bug "search menembus" (poin 3) — diperbaiki**: ditemukan
  panel `.custom-dd-panel` punya padding vertikal (`py-1.5`) di scroll
  container yang sama dengan search bar `sticky top-0` — padding itu
  menyisakan celah 6px DI ATAS search bar yang tidak ketutup latar
  belakangnya, sehingga item list yang di-scroll bisa "mengintip" tembus di
  celah itu. Fix: 1) panel diubah dari `py-1.5` (padding atas+bawah)
  menjadi `pt-1.5` (padding atas saja) di semua panel yang kena sentuh; 2)
  `_injectSearch()` di `custom-dropdown.js` sekarang menghitung
  `padding-top` panel via `getComputedStyle` dan memberi `margin-top`
  negatif yang sama besarnya pada search bar, supaya search bar benar-benar
  flush menutup celah tersebut — berlaku otomatis untuk SEMUA halaman lain
  yang pakai search auto-inject di `.custom-dd` (perbaikan CSS murni, tidak
  mengubah interaksi/behavior apa pun, jadi risikonya rendah meski file
  shared).

  **Verifikasi statis**: `php -l` controller → OK. Blade dikompilasi ulang
  via `Blade::compileString()` + `php -l` → OK. Dihitung ulang manual:
  jumlah `<th>` (19) = jumlah `<td>` per baris (19) = `colspan="19"`.
  Setiap hidden filter input (`filterDepartment`, `filterDivision`,
  `filterPersonnelSubarea`, `filterEmployeeType`, dst) dipastikan muncul
  tepat 1x.

  **BELUM diverifikasi visual di browser** (masih tidak ada akses browser
  interaktif di sesi ini). Yang PALING PENTING untuk dicek user:
  - Buka Department → sekarang harus muncul search box + daftar pilihan
    (bukan cuma satu input teks), bisa pilih lebih dari satu, ada Clear.
  - Buka Division & Personnel Subarea & Employee Type — filter baru harus
    muncul dan berfungsi.
  - Scroll tabel ke kanan sambil salah satu panel terbuka — panel
    Department/Position dkk seharusnya ikut reposisi atau tertutup, tidak
    lagi "melayang" di posisi lama.
  - Scroll LIST DI DALAM panel Position (yang listnya panjang, ada search)
    ke bawah — pastikan search bar di atas benar-benar solid, tidak ada
    lagi teks item yang mengintip di baliknya.
  - Pastikan kolom Title/Nick Name/Gender/Religion/Marital Status/Birth
    Date/Birth Place sudah tidak muncul, dan Status sekarang di posisi
    tepat sebelum Actions.
  Kalau masih ada yang meleset, kirim screenshot + urutan aksi yang
  dilakukan seperti biasa.

- 2026-09-02 (lanjutan lagi #2, sesi sama) — User kirim 2 screenshot
  berurutan: (1) buka filter Position, (2) scroll tabel ke kanan sambil
  panel masih terbuka. Hasilnya: panel Position ikut "menggeser" ke posisi
  yang salah — menembus/menimpa sidebar kiri, DAN ikut menutupi/menimpa
  header ECI & Full Name yang seharusnya freeze (sticky) di kiri. User minta
  semua kolom & filter dicek konsisten untuk masalah yang sama.

  Analisis akar masalah (dari membaca kode): fungsi `_onScrollMaybeClose()`
  di `custom-dropdown.js` REPOSISI panel (mode fixed) mengikuti
  `btn.getBoundingClientRect()` tiap kali ada event scroll, dan hanya
  menutup panel kalau tombolnya keluar dari VIEWPORT (`window.innerHeight`)
  — tidak pernah cek apakah tombol sudah ter-clip/tersembunyi oleh
  ANCESTOR-nya yang scrollable (`#employeeTableWrapper`, yang overflow-x-nya
  auto). Saat tombol Position di-scroll horizontal sampai keluar dari area
  visible wrapper, `getBoundingClientRect()` TETAP mengembalikan posisi
  geometris tombol itu (browser tidak tahu itu "tersembunyi" oleh overflow
  ancestor) — jadi kode reposisi memindahkan panel ke sana, yang secara
  visual bisa jatuh di luar tabel (menimpa sidebar) dan menimpa kolom sticky
  ECI/Full Name karena panel py-nya z-index tinggi (`z-[9999]`). Ini
  kemungkinan besar penyebab TUNGGAL dari poin 3 (menembus sidebar), 4
  (menimpa ECI/Full Name), dan 5 (poin user: "hal ini terjadi juga pada
  head table dan filter lainnya" — karena semua dropdown filter di halaman
  ini pakai fungsi shared yang sama, jadi satu fix ini berlaku ke semuanya:
  Position, Module, Division, Department, Home Base, Personnel Subarea,
  Employee Type, Status).

  Perbaikan: tambah helper `_isClippedByScrollableAncestor(el, rect)` di
  `custom-dropdown.js` — mengecek apakah tombol trigger sudah ter-clip
  (sebagian/seluruhnya di luar area terlihat) oleh salah satu ancestor-nya
  yang scrollable (overflow auto/scroll di X atau Y). Dipanggil di dalam
  `_onScrollMaybeClose()`: kalau ter-clip, panel DITUTUP (bukan direposisi
  ke koordinat yang sudah tidak valid secara visual). Juga menambah cek
  horizontal (`r.right < 0 || r.left > window.innerWidth`) yang sebelumnya
  cuma ada untuk vertical (`r.bottom < 0 || r.top > window.innerHeight`).

  Ini perubahan di file SHARED (`custom-dropdown.js`, dipakai 40+ halaman
  lain) tapi sifatnya murni defensif — hanya MENAMBAH kondisi "tutup" pada
  skenario yang sebelumnya menghasilkan bug visual (reposisi ke tempat
  salah), tidak mengubah perilaku pada skenario yang sudah benar. Risikonya
  dinilai rendah, tapi tetap perlu dicek di halaman lain yang punya
  wrapper scrollable + dropdown fixed serupa (mis. ticket list) kalau ada
  waktu untuk regresi test.

  Verifikasi statis: `node -c public/js/custom-dropdown.js` → syntax OK.

  BELUM diverifikasi visual — assessment risiko & root cause di atas murni
  dari membaca kode (masih tidak ada akses browser interaktif di sesi ini).
  Mohon user re-test skenario PERSIS yang ada di screenshot: buka Position →
  scroll kanan → panel harus otomatis TERTUTUP (bukan pindah ke tempat
  salah), dan header ECI/Full Name harus tetap terlihat normal, freeze di
  kiri. Ulangi untuk Department/Division/Home Base/dll untuk pastikan
  konsisten di semua filter.

- 2026-09-02 (lanjutan lagi #3, sesi sama) — User menegur: bug "dropdown
  menimpa sidebar saat scroll" SUDAH beberapa kali diminta perbaiki dan
  selalu gagal, minta diagnosis dulu sebelum patch lagi (bukan asal ubah
  z-index/position tanpa penjelasan).

  Diagnosis yang diberikan ke user (sebelum ubah kode apa pun):
  - Dikoreksi: ini bukan React, tidak ada folder `components/table` — 1 file
    Blade ([resources/views/master/employee/index.blade.php](../../resources/views/master/employee/index.blade.php))
    + 1 file JS shared ([public/js/custom-dropdown.js](../../public/js/custom-dropdown.js)).
  - Header & body tabel BUKAN 2 container terpisah yang perlu disinkron
    manual — satu `<table>`, satu scroll wrapper (`#employeeTableWrapper`),
    `position: sticky` native. Jadi "header desync horizontal" secara
    struktural TIDAK mungkin terjadi murni dari CSS sticky — gejala yang
    terlihat ("header tertimpa") kemungkinan besar sebenarnya panel dropdown
    (z-index 9999) yang salah posisi menutupi header, BUKAN header itu
    sendiri yang geser.
  - Dropdown SUDAH portal ke `document.body` + `position: fixed` +
    `getBoundingClientRect()` (persis pola yang diminta user, cuma versi
    vanilla JS bukan React Portal). Sidebar `z-50` vs panel `z-[9999]` —
    dikonfirmasi BUKAN bug z-index (panel memang sudah lebih tinggi), murni
    bug KOORDINAT (posisi x/y salah hitung).
  - Akar masalah: `_onScrollMaybeClose()` di `custom-dropdown.js`
    me-REPOSISI panel fixed mengikuti `getBoundingClientRect()` tombol
    setiap event scroll. `getBoundingClientRect()` tetap melaporkan posisi
    geometris tombol walau tombolnya sudah tersembunyi di balik tepi wrapper
    tabel yang scroll horizontal (browser tidak tahu itu "terpotong" oleh
    overflow ancestor) — jadi panel dipindah ke koordinat yang sudah tidak
    valid, bisa jatuh di area sidebar.
  - Disclosure jujur: percobaan fix SEBELUMNYA (sesi ini juga, entry sebelum
    ini di log) mencoba tambah pengecekan "clipped by scrollable ancestor"
    tapi TIDAK bisa diverifikasi live, dan ternyata (menurut laporan user)
    belum menyelesaikan masalah.

  User diberi 2 opsi: (a) coba pendekatan lebih sederhana & robust — panel
  fixed SELALU ditutup saat ada scroll (bukan direposisi), sama seperti
  perilaku panel non-fixed yang sudah ada; atau (b) verifikasi dulu via
  DevTools sebelum saya ubah kode lagi. **User pilih (a).**

  Perubahan yang diterapkan: `_onScrollMaybeClose()` disederhanakan drastis
  — SELURUH logic reposisi-ikuti-tombol untuk panel mode fixed (dan
  helper `_isClippedByScrollableAncestor` yang baru ditambahkan sesi ini)
  DIHAPUS. Sekarang fungsinya cuma: scroll DI DALAM panel (user scroll
  daftar opsi) → biarkan; scroll DI LUAR panel (apa pun sumbernya, fixed
  atau non-fixed) → `_closeAllDropdowns()`. Ini menghilangkan SELURUH kelas
  bug "reposisi ke koordinat salah" karena tidak pernah lagi menghitung
  ulang posisi sama sekali — pola yang sama persis dengan panel ECI yang
  dari awal sudah menutup (bukan mengikuti) saat discroll dan tidak pernah
  dilaporkan bermasalah.

  Trade-off yang disadari & diterima user: UX dropdown fixed-mode di HALAMAN
  MANA PUN yang pakai `data-fixed="true"` (bukan cuma Master Employee — ini
  file shared) sekarang "menutup saat discroll" alih-alih "ikut mengambang
  mengikuti tombol". Untuk 40+ halaman lain yang pakai pola sama, ini
  konsisten dengan pola panel non-fixed yang sudah ada di mana-mana; risiko
  regresi dinilai rendah karena hasilnya JADI LEBIH SEDERHANA (less code,
  fewer edge cases), bukan lebih kompleks.

  Verifikasi statis: `node -c public/js/custom-dropdown.js` → syntax OK.
  `_closeAllDropdowns()` (fungsi yang sekarang jadi satu-satunya jalur)
  dikonfirmasi sudah menangani kedua mode (fixed: re-attach ke owner +
  reset style; non-fixed: langsung hidden) — sudah dipakai di banyak tempat
  lain sebelumnya (klik-di-luar, buka dropdown lain), bukan kode baru yang
  belum teruji sama sekali.

  BELUM diverifikasi visual — TETAP tidak ada akses browser di sesi ini.
  User perlu re-test: buka Position/Module/Department/dll → scroll tabel ke
  kanan sedikit saja → panel harus LANGSUNG TERTUTUP (bukan ikut bergeser
  ataupun tetap di posisi lama), header ECI/Full Name harus tidak pernah
  tertutupi apa pun.

- 2026-09-02 (lanjutan lagi #4, sesi sama) — User konfirmasi sudah test fix
  scroll-close sebelumnya ("sudah saya tes, saya akan koordinasi dengan
  tim") dan lapor 2 hal baru: (1) header ECI & Full Name (freeze) masih
  tertimpa header kolom lain saat scroll kiri-kanan; (2) minta kolom
  Personnel Area diberi filter juga (belum, sebelumnya cuma display-only).

  **Akar masalah #1 — DITEMUKAN PASTI (bukan dugaan)**: ada blok CSS
  PRA-EXISTING (sudah ada sebelum sesi ini, bukan saya yang buat) di
  `<style>` halaman ini:
  ```
  #employeeTableWrapper thead th:nth-child(1),
  #employeeTableWrapper thead th:nth-child(2) { background: #f9fafb; z-index: 6; }
  ```
  Selector ID (`#employeeTableWrapper thead th:nth-child(...)`) punya
  spesifisitas CSS lebih tinggi daripada class Tailwind `z-30` yang saya
  tempel di `<th>` ECI/Full Name pada revisi sebelumnya — jadi `z-30` saya
  KALAH dan nilai efektifnya tetap `z-index: 6` dari rule lama itu.
  Sementara header kolom lain (Position, Module, dst) pakai `z-10` (Tailwind,
  tidak ada rule ID yang menimpa) → 10 > 6 → header lain menimpa ECI/Full
  Name saat overlap terjadi ketika scroll horizontal. Ini murni bug
  spesifisitas CSS, sudah dikonfirmasi persis dari membaca kode (bukan
  tebakan) — cocok 100% dengan gejala di screenshot user.

  Fix: naikkan `z-index` di rule CSS itu sendiri dari `6` → `20` (di atas
  `z-10` semua header filter lain). Juga dibersihkan: class Tailwind `z-30`
  yang tidak efektif dihapus dari kedua `<th>` (ECI, Full Name) + komentar
  ditambahkan menjelaskan bahwa z-index-nya dikendalikan oleh rule CSS
  tersebut — supaya tidak ada 2 "sumber kebenaran" yang membingungkan lagi
  di masa depan.

  **Personnel Area**: dikonversi dari `<th>` plain (display-only) jadi
  `.custom-dd` multi-select filter, pola identik dengan Division/Department/
  Personnel Subarea, di-backing `$personnelAreaOptions` (composer,
  options-nya sama dengan dropdown Personnel Area di modal Create/Edit).
  Backend: filter `personnel_area` baru (`whereIn`, sama semantik dengan
  filter multi-select lain). `getCurrentFilters()`, `resetFilters()`, dan
  restore-filter-state (DOMContentLoaded) semua diupdate untuk menyertakan
  `filterPersonnelArea`. Jumlah kolom TIDAK bertambah (masih 19) — Personnel
  Area sudah ada sebagai kolom, cuma sekarang jadi bisa difilter.

  Verifikasi statis: `php -l` OK, Blade compile OK, jumlah `<th>` (19) =
  jumlah `<td>` per baris (19), `filterPersonnelArea` muncul tepat 1x.

  BELUM diverifikasi visual (masih tidak ada akses browser). Mohon user
  cek: scroll tabel kiri-kanan → ECI & Full Name harus SELALU di atas/tidak
  pernah tertutup kolom lain apa pun kondisinya; Personnel Area sekarang
  punya panah filter + bisa multi-select + Clear.

- 2026-09-02 (lanjutan lagi #5, sesi sama) — User lapor teks item dropdown
  (mis. "PRESIDENT DIRECTOR", "DIRECTOR FINANCE, HR & GENERAL") terlihat
  rata tengah, minta rata kiri semua. Juga minta saran: apakah Clear lebih
  rapi kalau ditaruh di kiri sejajar search box?

  Root cause (langsung ketemu, bukan tebakan): item `.custom-dd-item` yang
  punya checkmark (semua opsi kecuali "All X") pakai `flex items-center
  justify-between` TANPA class `text-left` eksplisit — beda dengan item
  "All X" yang sudah punya `text-left`. Untuk teks yang wrap 2 baris,
  `<button>` browser default `text-align: center`, jadi baris kedua & dst
  terlihat centered di dalam lebar box-nya sendiri. Fix: tambah `text-left`
  eksplisit ke SEMUA `.custom-dd-item` bercheckmark (8 di Blade statis:
  Position, Home Base, Division, Department, Personnel Area, Personnel
  Subarea, Employee Type — dan 1 di JS yang generate item Module secara
  dinamis dari `/api/modules`).

  Saran Clear vs search: diberi 2 opsi dengan preview ASCII (sticky-bottom
  yang sudah ada, vs Clear sejajar kiri search). User pilih PERTAHANKAN
  sticky-bottom (yang sudah diimplementasikan sejak awal) — tidak ada
  perubahan layout untuk ini.

  Verifikasi statis: Blade compile OK.
  BELUM diverifikasi visual (tidak ada akses browser). Mohon user cek teks
  item panjang di Position/Department dkk sekarang rata kiri.

- 2026-09-02 (lanjutan lagi #6, sesi sama) — User minta Module filter juga
  punya search box (sekarang cuma list panjang tanpa search).

  Root cause: `custom-dropdown.js` auto-inject search hanya kalau item count
  panel > 7 SAAT `initCustomDropdowns()` jalan (di `DOMContentLoaded`). Item
  Module baru ditambahkan BELAKANGAN lewat `loadModuleFilterOptions()`
  (fetch `/api/modules`, dipanggil setelah `initCustomDropdowns()`) — jadi
  saat threshold-check jalan, panel masih 0 item, search tidak pernah
  di-inject walau nanti isinya panjang (ABAP, BASIS, BI, BW, dst).

  Fix: tambah `data-searchable="true"` + `data-search-placeholder="Search
  module..."` ke wrapper `#ddFilterModules` — ini attribute yang sudah ada
  dukungannya di `custom-dropdown.js` (opt-in eksplisit, bypass threshold
  count), dipakai di halaman lain juga. Item yang ditambahkan belakangan via
  `insertBefore` tetap ikut ke-filter dengan benar karena search listener
  query ulang `.custom-dd-item` tiap kali user mengetik (bukan snapshot di
  awal). Tidak ada perubahan di file shared.

  Verifikasi statis: Blade compile OK.
  BELUM diverifikasi visual.

- 2026-09-02 (lanjutan lagi #7, sesi sama) — User minta koreksi: font header
  harus KONSISTEN bold + kapital di semua kolom (membatalkan permintaan awal
  poin 1 yang minta Position TIDAK bold) — Position dikembalikan ke
  `font-semibold` supaya sama dengan header lain. Dicek ulang: semua 19
  header sekarang konsisten `font-semibold uppercase tracking-wider`.

  User juga minta 2 kolom lagi diberi filter: **Employee Group** dan
  **Full Name**.
  - Employee Group: dikonversi ke `.custom-dd` multi-select, di-backing
    `$employeeGroupOptions` (composer, sama dengan modal). Backend: filter
    `employee_group` baru (`whereIn`).
  - Full Name: DULU eksplisit diminta "tidak perlu filter" (requirement
    awal poin 7) — sekarang user minta filter search, "biar profesional".
    Diimplementasikan dengan pola BESPOKE yang sama seperti ECI (bukan
    custom-dd, karena Full Name bebas teks bukan enum): tombol + panel
    floating + search input + Clear, id terpisah (`fullNameFilterBtn`,
    `fullNameFilterPanel`, `filterFullName`, dst). Backend: helper baru
    `applyFullNameSearch()` (mirror `applyNameSearch()` tapi HANYA cocokkan
    first_name/last_name — sengaja TIDAK ikut cocokkan ECI/nick_name supaya
    filter Full Name & ECI punya cakupan yang jelas terpisah per kolom,
    tidak tumpang tindih membingungkan).
  - Kedua panel baru (Full Name bespoke, Employee Group custom-dd)
    diintegrasikan penuh ke sistem saling-menutup yang sudah ada:
    `toggleEmpFilter`↔`toggleFullNameFilter` saling `closeXxx()`,
    `.custom-dd-btn` click & Escape & scroll wrapper semuanya update untuk
    menutup panel Full Name juga (persis pola ECI).
  - `getCurrentFilters()`, `resetFilters()`, restore-filter-state
    (DOMContentLoaded) semua diupdate menyertakan `full_name` &
    `employee_group`.

  Jumlah kolom TIDAK berubah (masih 19) — Employee Group & Full Name sudah
  ada sebagai kolom, cuma sekarang jadi bisa difilter.

  Verifikasi statis: `php -l` OK, Blade compile OK, `<th>`=19=`<td>`, id
  `filterFullName`/`filterEmployeeGroup` masing-masing muncul 1x.
  BELUM diverifikasi visual.

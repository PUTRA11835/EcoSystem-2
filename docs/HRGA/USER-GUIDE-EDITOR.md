# Memasang User Guide Editor di branch HRGA

Dokumen ini untuk tim yang memegang branch HRGA. Isinya cara memasang User Guide Editor yang sama persis dengan branch `eco_config`, supaya saat kedua branch di-merge tidak ada konflik.

> **Status verifikasi (28 Okt 2026).** Editor sudah diuji penuh di branch `eco_config` (41 tes guide, pengujian langsung di browser, matriks 54 role). Di branch HRGA **belum diuji**, karena branch remote `aldy_hr`, `alnaf_hr` dan `nafa_hr` belum memuat mesin User Guide sama sekali (mesin itu masih di salinan lokal tim HRGA). Yang sudah dicek pada ketiga branch itu hanyalah titik tempel di file bersama, hasilnya ada di bagian 11. Sebelum dipakai, jalankan bagian 7 di salinan lokal HRGA.

## 1. Apa ini

Editor di **Control Center > User Guide Editor** (`/admin/user-guide`) memungkinkan administrator mengubah isi User Guide tanpa deploy.

- Topik Markdown di `resources/guide/{en,id}` tetap menjadi **sumber bawaan**.
- Satu baris di tabel `user_guide_topics` adalah salinan penuh satu topik untuk satu bahasa. Bila key-nya sama dengan topik berkas, baris itu **menimpa** topik tersebut (status *Edited*). Bila key-nya baru, ia menjadi topik tambahan (status *Custom*).
- **Restore default** menghapus baris itu, jadi pembaca kembali ke berkas.
- Aturan isi sama ketatnya dengan `docs/HRGA/tools/guide-check.php`: tanpa em dash atau en dash, tanpa HTML mentah, tanpa tautan keluar, gambar hanya `images/guide/*.png|jpg` yang ada dan ber-alt, `[[key]]` harus menuju topik yang ada, `requires` harus slug menu atau `@approver` atau `@role:Nama Role` yang ada.
- Dokumentasi rancangan lengkap ada di `resources/guide/_EDITOR.md` (ikut tersalin).

## 2. Prinsip anti konflik

1. **File baru disalin persis, byte per byte.** Jangan diubah di HRGA. Bila perlu perubahan, lakukan di satu tempat lalu salin ke branch lain.
2. **File bersama hanya disentuh di 5 titik kecil** (bagian 4). Semuanya tambahan, tidak ada baris yang dihapus.
3. **Nama berkas migrasi harus sama persis** (`2026_10_28_000001_...` dan `2026_10_28_000002_...`). Nama yang sama membuat Laravel mengenali keduanya sebagai migrasi yang sama setelah merge. Jangan diganti tanggalnya.
4. Setelah semua ini terpasang, kedua branch punya isi yang identik untuk fitur editor, sehingga merge menghasilkan tanpa perubahan.

## 3. File baru (salin persis)

```
app/Services/Guide/TopicOverrides.php
app/Services/Guide/DatabaseTopicOverrides.php
app/Services/Guide/GuideContentRules.php
app/Services/Guide/GuideTopicEditor.php
app/Providers/GuideServiceProvider.php
app/Models/UserGuideTopic.php
app/Http/Controllers/UserGuideAdminController.php
app/Http/Requests/SaveGuideTopicRequest.php
database/migrations/2026_10_28_000001_create_user_guide_topics_table.php
database/migrations/2026_10_28_000002_add_user_guide_editor_menu.php
resources/views/admin/user-guide/index.blade.php
resources/views/admin/user-guide/form.blade.php
resources/views/admin/user-guide/_picker.blade.php
resources/guide/_EDITOR.md
tests/Unit/Guide/GuideContentRulesTest.php
tests/Unit/Guide/GuideTopicEditorTest.php
```

Folder `app/Http/Requests` belum ada di banyak branch. Membuatnya tidak berbahaya.

**Dokumen ini berdiri sendiri.** Isi lengkap setiap file di atas ada di **Lampiran A** di bawah, sehingga tim HRGA cukup menyalin dari dokumen ini tanpa akses ke branch lain. Salin isi tiap blok persis, termasuk spasi dan baris kosong.

Cara menyalin alternatif, bila branch sumber sudah di-commit atau ada di remote:

```bash
git checkout <branch-eco_config> -- \
  app/Services/Guide/TopicOverrides.php \
  app/Services/Guide/DatabaseTopicOverrides.php \
  app/Services/Guide/GuideContentRules.php \
  app/Services/Guide/GuideTopicEditor.php \
  app/Providers/GuideServiceProvider.php \
  app/Models/UserGuideTopic.php \
  app/Http/Controllers/UserGuideAdminController.php \
  app/Http/Requests/SaveGuideTopicRequest.php \
  database/migrations/2026_10_28_000001_create_user_guide_topics_table.php \
  database/migrations/2026_10_28_000002_add_user_guide_editor_menu.php \
  resources/views/admin/user-guide \
  resources/guide/_EDITOR.md \
  tests/Unit/Guide/GuideContentRulesTest.php \
  tests/Unit/Guide/GuideTopicEditorTest.php
```

Bila belum di-commit, pakai Lampiran A.

## 4. Perubahan pada file bersama (5 titik)

### 4.1 `app/Services/Guide/UserGuideService.php`

Ini mesin bersama, jadi perubahannya sengaja minimal dan tidak mengubah perilaku bila tidak ada hasil sunting.

**a. Konstruktor opsional**, tepat setelah deklarasi konstanta:

```php
    public const DIR = 'guide';
    private const CACHE_VER = 'v1';

    /** Sumber tambahan (hasil sunting admin) di atas berkas; null = hanya berkas. */
    public function __construct(private readonly ?TopicOverrides $overrides = null)
    {
    }
```

`new UserGuideService()` tanpa argumen tetap berfungsi, jadi tes lama tidak berubah.

**b. `load()` menjadi berkas ditambah timpaan, dan pembacaan berkas pindah ke `loadFiles()`.** Ganti method `load()` yang ada dengan dua method ini (isi dalam `loadFiles()` adalah isi `load()` lama, tidak berubah):

```php
    public function load(string $locale, bool $strict): array
    {
        return ($this->overrides?->forLocale($locale) ?? []) + $this->loadFiles($locale, $strict);
    }

    /** Hanya berkas bawaan (tanpa hasil sunting). Dipakai pemeriksa dan editor. */
    public function loadFiles(string $locale, bool $strict = false): array
    {
        $out = [];
        // ... isi load() yang lama, tidak diubah ...
    }
```

**c. Versi timpaan masuk tanda tangan cache**, di method `signature()`, tepat setelah baris `config_path('user_guide.php')`:

```php
        $parts[] = (string) filemtime(config_path('user_guide.php'));
        $parts[] = $this->overrides?->version() ?? '';
```

Tanpa baris ini, hasil sunting tidak langsung terlihat karena cache 12 jam.

### 4.2 `bootstrap/providers.php`

Tambahkan satu baris ke daftar:

```php
    App\Providers\GuideServiceProvider::class,
```

### 4.3 `routes/web.php`

Di dalam grup `Route::prefix('admin')->name('admin.')->group(...)`, tambahkan blok ini (misalnya sebelum rute `sessions`):

```php
        Route::prefix('user-guide')->name('user-guide.')->middleware('menu:control-center.user-guide')->group(function () {
            $c = \App\Http\Controllers\UserGuideAdminController::class;
            Route::get('/', [$c, 'index'])->name('index');
            Route::get('/create', [$c, 'create'])->name('create');
            Route::post('/', [$c, 'store'])->name('store');
            Route::post('/preview', [$c, 'preview'])->name('preview');
            Route::get('/{key}', [$c, 'edit'])->name('edit')->where('key', '[a-z0-9\-]+');
            Route::put('/{key}', [$c, 'update'])->name('update')->where('key', '[a-z0-9\-]+');
            Route::delete('/{key}', [$c, 'destroy'])->name('destroy')->where('key', '[a-z0-9\-]+');
        });
```

Urutan `/create` sebelum `/{key}` penting. Nama rute yang dihasilkan: `admin.user-guide.index`, `.create`, `.store`, `.preview`, `.edit`, `.update`, `.destroy`.

### 4.4 `database/seeders/MenuSeeder.php`

Dua baris tambahan, supaya instalasi baru (seed ulang) ikut punya menu ini. Pada `aldy_hr`, `alnaf_hr` dan `nafa_hr` baris `control-center.two-factor-enforcement` **tidak ada**, jadi tempatkan setelah baris `control-center` terakhir yang ada (di sana `control-center.ai-settings`) dan setelah grant terakhir untuk `control-center.*`:

```php
// di daftar menu, setelah baris control-center terakhir:
['slug' => 'control-center.user-guide',    'name' => 'User Guide Editor',       'type' => 'page',     'parent_slug' => 'control-center', 'route_name' => 'admin.user-guide.index',   'icon' => null,                   'order_seq' => 12],

// di peta grant, setelah grant control-center terakhir:
'control-center.user-guide'   => [self::ADMIN=>$v],
```

Migrasi `..._add_user_guide_editor_menu` mengurus database yang sudah berjalan. Seeder mengurus instalasi baru. Keduanya aman dijalankan bersama karena sama-sama memeriksa menu yang sudah ada.

### 4.5 `resources/views/dashboard.blade.php` (item sidebar)

Di `eco_config` sidebar menulis tiap item Control Center secara eksplisit dengan `@if($can(...))`. Di `aldy_hr`, `alnaf_hr` dan `nafa_hr` file `dashboard.blade.php` **tidak memuat satu pun item `control-center`**, jadi sidebar-nya tampaknya dibentuk dari tabel `menu`. Kalau di salinan lokal HRGA juga begitu, **lewati langkah ini**: menu muncul sendiri setelah migrasi berjalan (baris `route_name` = `admin.user-guide.index`). Bila sidebar HRGA menulis item secara manual, tambahkan setelah item Control Center terakhir:

```blade
                        @if($can('control-center.user-guide'))
                        <a href="{{ route('admin.user-guide.index') }}" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-lg {{ Request::is('admin/user-guide*') ? 'bg-white/15 text-white font-medium' : 'text-white/70 hover:bg-white/10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-4 h-4 flex items-center justify-center"><i class="fas fa-book text-xs"></i></span>
                            <span class="nav-text text-sm">User Guide Editor</span>
                        </a>
                        @endif
```

## 5. Yang TIDAK perlu diubah

- `UserGuideController`, `config/user_guide.php`, tampilan `user-guide/index` dan `pdf`: editor hanya membaca lewat `UserGuideService`, jadi halaman pembaca otomatis menampilkan hasil sunting.
- Topik di `resources/guide`: tetap berkas biasa.
- Branch `eco_config` memakai izin semu `@role:Nama Role` di `UserGuideController`. Itu **tidak wajib** di HRGA. Editor tetap berjalan tanpa itu. Hanya saja daftar role di form "Visible to" akan ikut tampil, dan memilihnya tidak berpengaruh sampai controller mendukung `@role:`. Bila HRGA belum mengadopsi `@role:`, sebaiknya cukup centang menu access.

## 6. Prasyarat di branch HRGA

Semua ini sudah ada di basis kode yang sama, tetapi pastikan:

- Tabel `menu`, `role_menu`, `employee_role` dan menu induk dengan slug `control-center`.
- `App\Support\MenuRegistrar`, `App\Traits\Auditable` dan `AuditObserver`.
- Layout `dashboard.blade.php` dengan `@push('styles')`, `@push('scripts')`, helper `$can()` dan toast untuk flash `success` dan `error`.
- Paket yang sama dengan mesin guide: `league/commonmark`.

## 7. Langkah setelah menyalin

```bash
php artisan migrate        # membuat tabel user_guide_topics dan menu control-center.user-guide
php artisan route:list --name=admin.user-guide     # harus ada 7 rute
php artisan test tests/Unit/Guide
php docs/HRGA/tools/guide-check.php               # galat 0
```

Setelah itu:

1. Masuk sebagai EC Administrator, pastikan **Control Center > User Guide Editor** muncul di sidebar. Menu baru hanya aktif untuk EC Administrator. Role lain diberi lewat **Menu Access**.
2. Buka satu topik, ubah judul, simpan, lalu cek `/user-guide`. Perubahan harus langsung terlihat.
3. Pada topik itu pilih **Restore default**. Versi berkas harus kembali.
4. Coba menyimpan teks berisi em dash. Editor harus menolaknya dengan pesan.
5. Cek **Audit Log**: ada entri modul `User Guide` (isi artikel tidak dicatat).

## 8. Bila terjadi konflik saat merge

| File | Cara menyelesaikan |
|---|---|
| File baru (bagian 3) | Ambil versi mana saja bila byte-nya identik. Bila beda, ambil versi dari `eco_config` dan hubungi pemilik perubahan. |
| `UserGuideService.php` | Pertahankan **kedua** perubahan: tambahan editor (bagian 4.1) dan perubahan HRGA. Tiga titik editor saling terpisah dari logika lain. |
| `providers.php`, `routes/web.php`, `MenuSeeder.php`, `dashboard.blade.php` | Semuanya tambahan murni. Pertahankan semua baris dari kedua sisi. |
| Migrasi | Pastikan tidak ada dua berkas dengan isi sama tetapi nama berbeda. Bila ada, hapus yang namanya tidak mengikuti bagian 2 butir 3, lalu jalankan `php artisan migrate:status` untuk memastikan tidak ada yang tertunda dua kali. |

## 9. Aturan kerja bersama ke depan

- Perubahan pada editor dilakukan di satu branch lalu disalin ke yang lain, bukan ditulis dua kali.
- Perubahan aturan isi: `GuideContentRules.php` dan `GuideContentRulesTest.php`. Perubahan simpan atau baca: `GuideTopicEditor.php` dan `GuideTopicEditorTest.php`. Jangan menambah logika ke controller atau view.
- Jangan menambah baris ke `UserGuideService` di luar tiga titik pada bagian 4.1 tanpa memberi tahu pemilik branch lain.
- Topik di `resources/guide` dan gambar di `public/images/guide` tetap berkas biasa dan boleh berbeda antar branch selama key-nya tidak bentrok.

## 10. Catatan untuk branch HRGA

- Gambar di topik: mesin guide di branch `eco_config` mengizinkan gambar lokal `images/guide/*.png|jpg` (aturan paket asli melarang gambar). Editor mengikuti aturan itu. Bila HRGA tetap melarang gambar, ubah `GuideContentRules::bodyProblems()` dan `guide-check.php` bersamaan, dan kabari pemilik `eco_config`.
- Gambar tidak diunggah lewat editor. Gambar ditambahkan sebagai berkas di `public/images/guide` lewat repo.

## 11. Hasil pengecekan titik tempel pada branch HR remote

Dicek langsung dari `origin/aldy_hr`, `origin/alnaf_hr` dan `origin/nafa_hr` (ketiganya hasilnya sama):

| Titik | Hasil |
|---|---|
| `bootstrap/providers.php` | Ada, hanya `AppServiceProvider`. Cukup tambah satu baris `GuideServiceProvider`. |
| `routes/web.php` grup `admin` | Ada (`Route::prefix('admin')->name('admin.')`), dan rute `sessions` ada sebagai tempat sisip. |
| `MenuSeeder` | Ada, tetapi tanpa `control-center.two-factor-enforcement`. Pakai anchor pada 4.4. |
| `dashboard.blade.php` | Ada, tanpa item `control-center` eksplisit. Lihat 4.5. `$can()` ada, dan `@stack('styles')` serta `@stack('scripts')` ada. |
| `MenuRegistrar`, `Auditable`, `AuditObserver` | Ada. |
| `barryvdh/laravel-dompdf` dan `league/commonmark` | Ada di `composer.json` atau `composer.lock`. |
| Migrasi bertanggal `2026_10_28` | Tidak ada, jadi tidak ada tabrakan nama. |
| Berkas atau folder User Guide (`app/Services/Guide`, `resources/guide`, `UserGuideController`, `config/user_guide.php`) | **Tidak ada.** Mesin guide belum di-push, jadi kecocokan `UserGuideService` versi lokal HRGA dengan tiga titik pada 4.1 belum bisa dicek. |

## 12. Konfigurasi lain

Tidak ada variabel `.env`, queue, scheduler, atau paket Composer baru. Yang perlu dicek:

| Hal | Keterangan |
|---|---|
| `config/user_guide.php` | Editor membaca `locales` (daftar bahasa) dan `chapters` (id bab) dari sini. Id bab pada topik atau form harus ada di `chapters`. Topik administrasi di branch `eco_config` memakai bab `administration`, dan panduan role memakai `reference`. Bila HRGA memakai id bab lain, topik di berkas tidak terpengaruh, hanya pilihan bab di form mengikuti config HRGA. Isi config `eco_config` ada di Lampiran B sebagai referensi. **Jangan menimpa config HRGA.** |
| Cache | Mesin guide memakai `Cache::remember` (12 jam) dengan kunci yang memuat `version()` dari hasil sunting. Tidak perlu `cache:clear` setelah simpan. Store cache bebas (file, database, redis). |
| Database | Satu tabel baru `user_guide_topics` (unik pada `key` dan `locale`). Kolom `requires` bertipe JSON, jadi butuh MySQL 5.7 atau lebih baru. |
| Sesi | Controller memakai `session('user.id')` untuk kolom `edited_by`. Bila kunci sesi di HRGA sama (default basis kode ini), tidak ada yang perlu diubah. |
| Izin | Menu `control-center.user-guide`, hanya EC Administrator secara bawaan (aturan `MenuRegistrar`). Role lain diberi lewat Menu Access. |
| Gambar | `public/images/guide/*.png|jpg`. Editor tidak mengunggah gambar. |
| Pengujian | `phpunit.xml` memakai sqlite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Ekstensi PHP `pdo_sqlite` harus aktif. `GuideTopicEditorTest` membuat tabel yang dibutuhkannya sendiri. |
| Audit | Model memakai trait `Auditable` dengan modul "User Guide". Kolom `body` dikecualikan dari catatan audit. |
| Layout | View editor memakai `@extends('dashboard')`, `@push('styles')`, `@push('scripts')`, variabel warna tema `--primary-rgb` dan `--primary-dark-rgb`, dan flash `success` atau `error` yang ditampilkan toast milik `dashboard.blade.php`. |

---

## Lampiran A. Isi lengkap file baru

Salin tiap blok ke path yang tertera. Isinya identik dengan branch `eco_config`.

### `app/Services/Guide/TopicOverrides.php`

````php
<?php

namespace App\Services\Guide;

/**
 * Sumber topik tambahan di atas berkas bawaan (mis. hasil sunting admin).
 * UserGuideService hanya mengenal antarmuka ini, bukan database.
 */
interface TopicOverrides
{
    /**
     * Topik satu bahasa dengan bentuk yang sama seperti UserGuideService::parseFile().
     *
     * @return array<string,array<string,mixed>> key => topik
     */
    public function forLocale(string $locale): array;

    /** Berubah setiap kali isi sumber berubah; ikut dalam tanda tangan cache. */
    public function version(): string;
}
````

### `app/Services/Guide/DatabaseTopicOverrides.php`

````php
<?php

namespace App\Services\Guide;

use App\Models\UserGuideTopic;
use Illuminate\Support\Facades\Schema;

/**
 * Topik hasil sunting admin dari tabel user_guide_topics.
 * Bila tabel belum ada (migrasi belum jalan) atau DB bermasalah, panduan tetap tampil dari berkas.
 */
final class DatabaseTopicOverrides implements TopicOverrides
{
    /** @var array<string,array<string,array<string,mixed>>>|null */
    private ?array $byLocale = null;
    private ?string $version = null;

    public function forLocale(string $locale): array
    {
        return $this->all()[$locale] ?? [];
    }

    public function version(): string
    {
        $this->all();

        return $this->version ?? '0';
    }

    /** Dibaca sekali per proses (satu permintaan web). */
    private function all(): array
    {
        if ($this->byLocale !== null) {
            return $this->byLocale;
        }
        $this->byLocale = [];
        $this->version = '0';

        try {
            if (!Schema::hasTable('user_guide_topics')) {
                return $this->byLocale;
            }
            $rows = UserGuideTopic::query()->get();
            foreach ($rows as $row) {
                $this->byLocale[$row->locale][$row->key] = $row->toTopic();
            }
            // Hash seluruh isi, bukan updated_at: dua penyuntingan dalam detik yang sama (atau hanya judul/syarat yang berubah) tetap membuang cache.
            $this->version = md5($rows->toJson());
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->byLocale;
    }
}
````

### `app/Services/Guide/GuideContentRules.php`

````php
<?php

namespace App\Services\Guide;

/**
 * Aturan isi topik User Guide yang disunting lewat UI. Sama ketatnya dengan pemeriksa berkas
 * (docs/HRGA/tools/guide-check.php), supaya hasil sunting tidak merusak panduan.
 *
 * Kelas ini murni: semua data acuan (key topik, slug menu, nama role) diberikan lewat konstruktor,
 * jadi bisa diuji tanpa database.
 */
final class GuideContentRules
{
    public const APPROVER = '@approver';
    public const ROLE_PREFIX = '@role:';

    /**
     * @param  string[]  $topicKeys   semua key topik yang ada (untuk tautan [[key]])
     * @param  string[]  $chapterIds
     * @param  string[]  $menuSlugs
     * @param  string[]  $roleNames
     * @param  string    $imageDir    folder gambar panduan di disk
     */
    public function __construct(
        private readonly array $topicKeys,
        private readonly array $chapterIds,
        private readonly array $menuSlugs,
        private readonly array $roleNames,
        private readonly string $imageDir,
    ) {
    }

    /**
     * @param  array{key:string,chapter:string,title:string,summary:string,requires:string[],body:string}  $draft
     * @return array<string,string[]> field => daftar pesan (kosong bila lolos)
     */
    public function violations(array $draft): array
    {
        $errors = [];
        $add = function (string $field, string $msg) use (&$errors) {
            $errors[$field][] = $msg;
        };

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $draft['key']) || strlen($draft['key']) > 100) {
            $add('key', 'Key may only contain lowercase letters, digits and hyphens (max 100 characters).');
        }
        if (!in_array($draft['chapter'], $this->chapterIds, true)) {
            $add('chapter', 'Unknown chapter.');
        }
        if (trim($draft['title']) === '') {
            $add('title', 'Title is required.');
        }

        foreach (['title', 'summary', 'body'] as $field) {
            if (preg_match('/[\x{2013}\x{2014}]/u', $draft[$field])) {
                $add($field, 'Remove em dashes and en dashes; use a comma, colon or full stop instead.');
            }
        }
        if (trim($draft['body']) === '') {
            $add('body', 'Content is required.');
        }

        foreach ($this->requiresProblems($draft['requires']) as $msg) {
            $add('requires', $msg);
        }
        foreach ($this->bodyProblems($draft['body'], $draft['key']) as $msg) {
            $add('body', $msg);
        }

        return $errors;
    }

    /** @param string[] $requires @return string[] */
    private function requiresProblems(array $requires): array
    {
        $out = [];
        foreach ($requires as $req) {
            if ($req === self::APPROVER) {
                continue;
            }
            if (str_starts_with($req, self::ROLE_PREFIX)) {
                if (!in_array(substr($req, strlen(self::ROLE_PREFIX)), $this->roleNames, true)) {
                    $out[] = "Unknown role in \"$req\".";
                }
                continue;
            }
            if (!in_array($req, $this->menuSlugs, true)) {
                $out[] = "Unknown menu permission \"$req\".";
            }
        }

        return $out;
    }

    /** @return string[] */
    private function bodyProblems(string $body, string $ownKey): array
    {
        $out = [];

        // Tanpa HTML mentah (komentar HTML tetap boleh) dan tanpa tautan keluar.
        $withoutComments = preg_replace('/<!--.*?-->/s', '', $body);
        if (preg_match('/<\/?[a-z][^>]*>/i', $withoutComments)) {
            $out[] = 'Raw HTML is not allowed; use Markdown.';
        }
        if (preg_match('/\]\(\s*(?:[a-z][a-z0-9+.\-]*:|\/\/)/i', $body) || preg_match('/^\s*\[[^\]]+\]:\s*(?:[a-z][a-z0-9+.\-]*:|\/\/)/im', $body)) {
            $out[] = 'External links are not allowed in the guide.';
        }

        preg_match_all('/!\[([^\]]*)\]\(([^)\s]*)[^)]*\)/', $body, $images, PREG_SET_ORDER);
        foreach ($images as [$whole, $alt, $src]) {
            if (trim($alt) === '') {
                $out[] = "Image \"$src\" needs alt text.";
            }
            if (!preg_match('#^images/guide/([A-Za-z0-9._\-]+\.(?:png|jpg))$#', $src, $m) || !is_file($this->imageDir . DIRECTORY_SEPARATOR . $m[1])) {
                $out[] = "Image \"$src\" must be an existing images/guide/*.png or *.jpg file.";
            }
        }

        preg_match_all('/\[\[([^\]|]+)(?:\|[^\]]*)?\]\]/', $body, $links);
        foreach (array_unique($links[1]) as $target) {
            if ($target === $ownKey || !in_array($target, $this->topicKeys, true)) {
                $out[] = $target === $ownKey ? "Topic links to itself: [[$target]]." : "Link [[$target]] points to a topic that does not exist.";
            }
        }

        return $out;
    }
}
````

### `app/Services/Guide/GuideTopicEditor.php`

````php
<?php

namespace App\Services\Guide;

use App\Models\UserGuideTopic;
use Illuminate\Support\Facades\DB;

/**
 * Logika penyuntingan User Guide dari Control Center: daftar status topik, baca, simpan, kembalikan ke bawaan, pratinjau.
 * Controller hanya mengurus HTTP; semua aturan ada di sini dan di GuideContentRules.
 */
final class GuideTopicEditor
{
    public const STATE_DEFAULT = 'default';
    public const STATE_EDITED = 'edited';
    public const STATE_CUSTOM = 'custom';

    public function __construct(private readonly UserGuideService $guide)
    {
    }

    /**
     * Satu baris per topik untuk satu bahasa: bawaan berkas, bawaan yang disunting, atau topik baru dari admin.
     *
     * @return array<int,array{key:string,title:string,chapter:string,state:string,stale:bool,translated:bool,requires:string[]}>
     */
    public function rows(string $locale): array
    {
        $files = $this->guide->loadFiles($locale);
        $enFiles = $locale === 'en' ? $files : $this->guide->loadFiles('en');
        $saved = UserGuideTopic::query()->where('locale', $locale)->get()->keyBy('key');
        $keys = array_unique(array_merge(array_keys($enFiles), array_keys($files), $saved->keys()->all()));

        $rows = [];
        foreach ($keys as $key) {
            $row = $saved[$key] ?? null;
            $base = $files[$key] ?? $enFiles[$key] ?? null;
            $topic = $row?->toTopic() ?? $base;
            if ($topic === null) {
                continue;
            }
            $rows[] = [
                'key'        => $key,
                'title'      => $topic['title'],
                'chapter'    => $topic['chapter'],
                'order'      => $topic['order'],
                'requires'   => $topic['requires'],
                'state'      => $row === null ? self::STATE_DEFAULT : ($this->hasFile($key) ? self::STATE_EDITED : self::STATE_CUSTOM),
                'stale'      => $row !== null && $row->base_hash !== null && $row->base_hash !== $this->fileHash($key, $locale),
                'translated' => $row !== null || isset($files[$key]),
            ];
        }

        $chapterOrder = array_flip(array_keys(config('user_guide.chapters')));
        usort($rows, fn ($a, $b) => [$chapterOrder[$a['chapter']] ?? 99, $a['order'], $a['key']] <=> [$chapterOrder[$b['chapter']] ?? 99, $b['order'], $b['key']]);

        return $rows;
    }

    /**
     * Isi formulir untuk satu topik: hasil sunting bila ada, bila tidak versi berkas (bahasa itu, lalu Inggris sebagai titik awal terjemahan).
     *
     * @return array<string,mixed>|null
     */
    public function find(string $key, string $locale): ?array
    {
        $saved = UserGuideTopic::query()->where(['key' => $key, 'locale' => $locale])->first();
        $file = $this->guide->loadFiles($locale)[$key] ?? null;
        $topic = $saved?->toTopic() ?? $file ?? $this->guide->loadFiles('en')[$key] ?? null;
        if ($topic === null) {
            return null;
        }

        return $topic + [
            'state'    => $saved === null ? self::STATE_DEFAULT : ($this->hasFile($key) ? self::STATE_EDITED : self::STATE_CUSTOM),
            'has_file' => $this->hasFile($key),
            'stale'    => $saved !== null && $saved->base_hash !== null && $saved->base_hash !== $this->fileHash($key, $locale),
            'borrowed' => $saved === null && $file === null,
        ];
    }

    /** @param array{chapter:string,title:string,summary:string,requires:string[],body:string,order:int} $data */
    public function save(string $key, string $locale, array $data, ?int $employeeId): UserGuideTopic
    {
        $existing = UserGuideTopic::query()->where(['key' => $key, 'locale' => $locale])->first();

        return UserGuideTopic::query()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            [
                'chapter'    => $data['chapter'],
                'sort_order' => $data['order'],
                'title'      => trim($data['title']),
                'summary'    => trim($data['summary']),
                'requires'   => array_values(array_unique($data['requires'])),
                'body'       => trim(str_replace("\r\n", "\n", $data['body'])),
                'updated_on' => now()->toDateString(),
                // Titik acuan perbandingan dengan berkas bawaan diambil sekali, saat topik pertama kali disunting.
                'base_hash'  => $existing?->base_hash ?? $this->fileHash($key, $locale),
                'edited_by'  => $employeeId,
            ]
        );
    }

    /** Hapus hasil sunting. Topik bawaan kembali ke berkas; topik baru dari admin ikut hilang. */
    public function reset(string $key, string $locale): bool
    {
        $row = UserGuideTopic::query()->where(['key' => $key, 'locale' => $locale])->first();

        return $row !== null && $row->delete();
    }

    /** Key sudah dipakai topik berkas atau topik admin di bahasa mana pun. */
    public function keyExists(string $key): bool
    {
        return $this->hasFile($key) || UserGuideTopic::query()->where('key', $key)->exists();
    }

    public function rules(): GuideContentRules
    {
        return new GuideContentRules(
            $this->allKeys(),
            array_keys(config('user_guide.chapters')),
            DB::table('menu')->pluck('slug')->all(),
            DB::table('employee_role')->pluck('name')->all(),
            public_path('images/guide'),
        );
    }

    /** Pratinjau Markdown dengan aturan render yang sama seperti halaman asli. */
    public function preview(string $markdown, string $key, string $locale): string
    {
        $titles = [];
        foreach ($this->rows($locale) as $row) {
            $titles[$row['key']] = $row['title'];
        }
        $html = $this->guide->render($markdown, $key ?: 'preview', $locale, $titles)['html'];

        // Gambar panduan dilayani dari public/, bukan relatif terhadap halaman editor.
        return str_replace('src="images/guide/', 'src="' . asset('images/guide') . '/', $html);
    }

    /** @return string[] */
    public function chapters(): array
    {
        return array_keys(config('user_guide.chapters'));
    }

    /** Pilihan untuk formulir: menu, role. @return array{menus:array<int,object>,roles:string[]} */
    public function requirementOptions(): array
    {
        return [
            'menus' => DB::table('menu')->orderBy('slug')->get(['slug', 'name'])->all(),
            'roles' => DB::table('employee_role')->orderBy('name')->pluck('name')->all(),
        ];
    }

    /** @return string[] */
    private function allKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->guide->loadFiles('en')),
            array_keys($this->guide->loadFiles('id')),
            UserGuideTopic::query()->pluck('key')->all(),
        )));
    }

    private function hasFile(string $key): bool
    {
        return $this->fileFor($key, 'en') !== null || $this->fileFor($key, 'id') !== null;
    }

    /** md5 isi berkas mentah topik di bahasa itu; null bila tidak ada berkasnya. */
    private function fileHash(string $key, string $locale): ?string
    {
        $file = $this->fileFor($key, $locale);

        return $file === null ? null : md5(str_replace("\r\n", "\n", (string) file_get_contents($file)));
    }

    /** @var array<string,array<string,string>> locale => key => path */
    private array $fileIndex = [];

    private function fileFor(string $key, string $locale): ?string
    {
        if (!isset($this->fileIndex[$locale])) {
            $this->fileIndex[$locale] = [];
            foreach ($this->guide->files($locale) as $file) {
                $parsed = $this->guide->parseFile($file);
                if ($parsed !== null) {
                    $this->fileIndex[$locale][$parsed['key']] = $file;
                }
            }
        }

        return $this->fileIndex[$locale][$key] ?? null;
    }
}
````

### `app/Providers/GuideServiceProvider.php`

````php
<?php

namespace App\Providers;

use App\Services\Guide\DatabaseTopicOverrides;
use App\Services\Guide\TopicOverrides;
use Illuminate\Support\ServiceProvider;

/** Menyambungkan hasil sunting admin (database) ke mesin User Guide. */
class GuideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TopicOverrides::class, DatabaseTopicOverrides::class);
    }
}
````

### `app/Models/UserGuideTopic.php`

````php
<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Salinan penuh satu topik User Guide untuk satu bahasa (lihat migrasi untuk semantiknya). */
class UserGuideTopic extends Model
{
    use Auditable;

    protected static string $auditModule = 'User Guide';

    /** Isi artikel panjang; yang dicatat cukup metadatanya. */
    protected static array $auditExcept = ['body'];

    protected $fillable = [
        'key', 'locale', 'chapter', 'sort_order', 'title', 'summary',
        'requires', 'body', 'updated_on', 'base_hash', 'edited_by',
    ];

    protected $casts = [
        'requires'   => 'array',
        'sort_order' => 'integer',
        'updated_on' => 'date:Y-m-d',
    ];

    /** Bentuk yang sama dengan hasil UserGuideService::parseFile(). */
    public function toTopic(): array
    {
        return [
            'key'      => $this->key,
            'chapter'  => $this->chapter,
            'order'    => $this->sort_order,
            'title'    => $this->title,
            'summary'  => $this->summary,
            'requires' => array_values((array) $this->requires),
            'updated'  => $this->updated_on?->format('Y-m-d'),
            'body'     => trim($this->body),
        ];
    }
}
````

### `app/Http/Controllers/UserGuideAdminController.php`

````php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGuideTopicRequest;
use App\Services\Guide\GuideTopicEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * Control Center → User Guide Editor. Hanya HTTP: aturan dan penyimpanan ada di
 * GuideTopicEditor, validasi isi di SaveGuideTopicRequest + GuideContentRules.
 */
class UserGuideAdminController extends Controller
{
    public function __construct(private readonly GuideTopicEditor $editor)
    {
    }

    public function index(Request $request): View
    {
        $locale = $this->locale($request->query('lang'));

        return view('admin.user-guide.index', [
            'locale'   => $locale,
            'rows'     => $this->editor->rows($locale),
            'chapters' => config('user_guide.chapters'),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($this->locale($request->query('lang')), [
            'key' => '', 'chapter' => 'getting-started', 'order' => 100, 'title' => '', 'summary' => '',
            'requires' => [], 'body' => '', 'state' => GuideTopicEditor::STATE_CUSTOM,
            'has_file' => false, 'stale' => false, 'borrowed' => false, 'is_new' => true,
        ]);
    }

    public function store(SaveGuideTopicRequest $request): RedirectResponse
    {
        return $this->persist($request, 'Topic created.');
    }

    public function edit(Request $request, string $key): View
    {
        $locale = $this->locale($request->query('lang'));
        $topic = $this->editor->find($key, $locale) ?? abort(404);

        return $this->form($locale, $topic + ['is_new' => false]);
    }

    public function update(SaveGuideTopicRequest $request, string $key): RedirectResponse
    {
        return $this->persist($request, 'Topic saved. Readers see the change immediately.');
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        $locale = $this->locale($request->input('locale'));
        $topic = $this->editor->find($key, $locale);
        $wasCustom = $topic !== null && !$topic['has_file'];

        if (!$this->editor->reset($key, $locale)) {
            return back()->with('error', 'Nothing to reset: this topic has no edits.');
        }

        return redirect()->route('admin.user-guide.index', ['lang' => $locale])
            ->with('success', $wasCustom ? 'Custom topic deleted.' : 'Topic restored to the default version.');
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body'   => ['required', 'string', 'max:200000'],
            'key'    => ['nullable', 'string', 'max:100'],
            'locale' => ['required', 'in:' . implode(',', array_keys(config('user_guide.locales')))],
        ]);

        return response()->json(['html' => $this->editor->preview($data['body'], (string) ($data['key'] ?? ''), $data['locale'])]);
    }

    private function persist(SaveGuideTopicRequest $request, string $message): RedirectResponse
    {
        $topic = $request->topic();
        $this->editor->save($topic['key'], $topic['locale'], $topic, (int) session('user.id') ?: null);

        return redirect()->route('admin.user-guide.edit', ['key' => $topic['key'], 'lang' => $topic['locale']])
            ->with('success', $message);
    }

    private function form(string $locale, array $topic): View
    {
        return view('admin.user-guide.form', [
            'locale'   => $locale,
            'topic'    => $topic,
            'chapters' => config('user_guide.chapters'),
            'options'  => $this->editor->requirementOptions(),
        ]);
    }

    /** Bahasa yang sah, bila tidak bahasa bawaan. */
    private function locale(?string $requested): string
    {
        $allowed = array_keys(config('user_guide.locales'));

        return in_array($requested, $allowed, true) ? $requested : (string) config('user_guide.default_locale', 'en');
    }
}
````

### `app/Http/Requests/SaveGuideTopicRequest.php`

````php
<?php

namespace App\Http\Requests;

use App\Services\Guide\GuideTopicEditor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form editor User Guide. Bentuk data diperiksa di sini; aturan isi
 * (tanda hubung, HTML, gambar, tautan, requires) ada di GuideContentRules.
 *
 * Akses dijaga oleh middleware menu:control-center.user-guide pada route.
 */
class SaveGuideTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key'        => ['required', 'string', 'max:100'],
            'locale'     => ['required', 'in:' . implode(',', array_keys(config('user_guide.locales')))],
            'chapter'    => ['required', 'string', 'max:50'],
            'order'      => ['required', 'integer', 'between:0,9999'],
            'title'      => ['required', 'string', 'max:200'],
            'summary'    => ['nullable', 'string', 'max:500'],
            'requires'   => ['nullable', 'array'],
            'requires.*' => ['string', 'max:150'],
            'body'       => ['required', 'string', 'max:200000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Pada update, key diambil dari URL dan tidak bisa diubah lewat form.
            'key'      => $this->route('key') ?? $this->input('key'),
            'summary'  => (string) $this->input('summary', ''),
            'requires' => array_values(array_filter((array) $this->input('requires', []), 'is_string')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $editor = app(GuideTopicEditor::class);
            $data = $v->validated();

            // Topik baru tidak boleh memakai key yang sudah ada.
            if ($this->route('key') === null && $editor->keyExists($data['key'])) {
                $v->errors()->add('key', 'This key is already used by another topic.');

                return;
            }

            foreach ($editor->rules()->violations($data) as $field => $messages) {
                foreach ($messages as $message) {
                    $v->errors()->add($field, $message);
                }
            }
        });
    }

    /** @return array{key:string,locale:string,chapter:string,order:int,title:string,summary:string,requires:string[],body:string} */
    public function topic(): array
    {
        $d = $this->validated();

        return [
            'key'      => (string) $d['key'],
            'locale'   => $d['locale'],
            'chapter'  => $d['chapter'],
            'order'    => (int) $d['order'],
            'title'    => $d['title'],
            'summary'  => (string) ($d['summary'] ?? ''),
            'requires' => $d['requires'] ?? [],
            'body'     => $d['body'],
        ];
    }
}
````

### `database/migrations/2026_10_28_000001_create_user_guide_topics_table.php`

````php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lapisan sunting User Guide.
 *
 * Topik bawaan tetap berupa berkas di resources/guide. Satu baris di tabel ini
 * adalah salinan penuh satu topik untuk satu bahasa: menimpa topik berkas yang
 * key-nya sama, atau menjadi topik baru bila key-nya tidak ada di berkas.
 * Menghapus baris = kembali ke versi berkas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guide_topics', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('locale', 5);
            $table->string('chapter', 50);
            $table->unsignedInteger('sort_order')->default(100);
            $table->string('title', 200);
            $table->string('summary', 500)->default('');
            $table->json('requires')->nullable();
            $table->longText('body');
            $table->date('updated_on')->nullable();
            // md5 isi berkas saat topik pertama kali disunting; null untuk topik baru.
            // Dipakai untuk memberi tahu admin bila berkas bawaan berubah setelah itu.
            $table->char('base_hash', 32)->nullable();
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guide_topics');
    }
};
````

### `database/migrations/2026_10_28_000002_add_user_guide_editor_menu.php`

````php
<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Control Center → User Guide Editor.
 *
 * Baris ditulis manual karena MenuRegistrar::register() tidak mengisi
 * route_name (pola sama dengan Two-Factor Enforcement). Grant lewat
 * MenuRegistrar: menu BARU aktif HANYA untuk EC Administrator.
 */
return new class extends Migration
{
    private const SLUG = 'control-center.user-guide';

    public function up(): void
    {
        $parentId = DB::table('menu')->where('slug', 'control-center')->value('id');

        if (!$parentId) {
            return;
        }

        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => $parentId,
                'name'       => 'User Guide Editor',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'admin.user-guide.index',
                'icon'       => 'fa-book',
                'order_seq'  => 12,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MenuRegistrar::grantToAdminOnly([self::SLUG]);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
````

### `resources/views/admin/user-guide/index.blade.php`

````blade
@extends('dashboard')

@section('title', 'User Guide Editor')
@section('page-title', 'User Guide Editor')
@section('page-subtitle', 'Edit the manual employees see at User Guide. Bundled topics stay as the default you can always restore.')

@push('styles')
<style>
    .uge-scope { --uge-accent: rgb(var(--primary-rgb, 153, 27, 27)); --uge-accent-dark: rgb(var(--primary-dark-rgb, 127, 29, 29)); --uge-ring: rgba(var(--primary-rgb, 153, 27, 27), 0.18); --uge-tint: rgba(var(--primary-rgb, 153, 27, 27), 0.08); font-family: inherit; }
    .uge-scope input, .uge-scope select, .uge-scope textarea, .uge-scope button { font-family: inherit; }
    .uge-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .uge-tabs { display: inline-flex; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; background: #fff; }
    .uge-tabs a { padding: 8px 16px; font-size: 13px; color: #4b5563; text-decoration: none; }
    .uge-tabs a.is-on { background: var(--uge-accent); color: #fff; font-weight: 600; }
    .uge-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; background: var(--uge-accent); color: #fff; }
    .uge-btn:hover { background: var(--uge-accent-dark); }
    .uge-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .uge-chapter { padding: 10px 20px; background: #f9fafb; border-bottom: 1px solid #f3f4f6; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; }
    .uge-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #f3f4f6; }
    .uge-row:last-child { border-bottom: 0; }
    .uge-title { font-size: 14px; font-weight: 600; color: #111827; text-decoration: none; }
    .uge-title:hover { color: var(--uge-accent); }
    .uge-meta { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .uge-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 6px; }
    .uge-pill.default { background: #f3f4f6; color: #4b5563; }
    .uge-pill.edited { background: #fecaca; color: #7f1d1d; }
    .uge-pill.custom { background: #dcfce7; color: #166534; }
    .uge-pill.stale { background: #fef3c7; color: #92400e; }
    .uge-pill.todo { background: #fee2e2; color: #991b1b; }
    .uge-actions { display: flex; gap: 14px; font-size: 13px; flex-shrink: 0; }
    .uge-actions a { color: var(--uge-accent); text-decoration: none; }
    .uge-note { font-size: 12px; color: #4b5563; background: var(--uge-tint); border: 1px solid #fecaca; border-radius: 10px; padding: 10px 14px; line-height: 1.6; }
</style>
@endpush

@section('content')
<div class="space-y-5 uge-scope">
    <div class="uge-note">
        Employees only see topics that match their menu access or role, as set in each topic's <strong>Visible to</strong>.
        A topic marked <span class="uge-pill stale" style="margin-left:0">default changed</span> was edited here, and its bundled file has changed since,
        so review it. Images are files in <code>public/images/guide</code> and cannot be uploaded here.
    </div>

    <div class="uge-bar">
        <div class="uge-tabs">
            @foreach (config('user_guide.locales') as $code => $label)
                <a href="{{ route('admin.user-guide.index', ['lang' => $code]) }}" class="{{ $locale === $code ? 'is-on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <a class="uge-btn" href="{{ route('admin.user-guide.create', ['lang' => $locale]) }}"><i class="fas fa-plus text-xs"></i> New topic</a>
    </div>

    @php($byChapter = collect($rows)->groupBy('chapter'))
    <div class="uge-card">
        @forelse ($chapters as $chapterId => $def)
            @continue(!$byChapter->has($chapterId))
            <div class="uge-chapter">{{ $def['title'][$locale] ?? $def['title']['en'] }}</div>
            @foreach ($byChapter[$chapterId] as $row)
                <div class="uge-row">
                    <div class="min-w-0">
                        <a class="uge-title" href="{{ route('admin.user-guide.edit', ['key' => $row['key'], 'lang' => $locale]) }}">{{ $row['title'] }}</a>
                        <span class="uge-pill {{ $row['state'] }}">{{ ['default' => 'Default', 'edited' => 'Edited', 'custom' => 'Custom'][$row['state']] }}</span>
                        @if ($row['stale'])<span class="uge-pill stale">default changed</span>@endif
                        @if (!$row['translated'])<span class="uge-pill todo">not translated</span>@endif
                        <div class="uge-meta">
                            {{ $row['key'] }}
                            &middot; {{ $row['requires'] ? 'Visible to: ' . implode(', ', array_map(fn ($r) => str_replace('@role:', 'role ', $r), $row['requires'])) : 'Visible to everyone' }}
                        </div>
                    </div>
                    <div class="uge-actions">
                        <a href="{{ route('user-guide.index', ['lang' => $locale]) }}#topic-{{ $row['key'] }}" target="_blank" rel="noopener">View</a>
                        <a href="{{ route('admin.user-guide.edit', ['key' => $row['key'], 'lang' => $locale]) }}">Edit</a>
                    </div>
                </div>
            @endforeach
        @empty
            <div class="uge-row">No topics found.</div>
        @endforelse
    </div>
</div>
@endsection
````

### `resources/views/admin/user-guide/form.blade.php`

````blade
@extends('dashboard')

@php
    $isNew = $topic['is_new'];
    $pick = old('requires', $topic['requires']);
    $has = fn (string $v) => in_array($v, (array) $pick, true);
    $localeLabel = config('user_guide.locales')[$locale] ?? $locale;
    $title = $isNew ? 'New topic' : 'Edit topic';
@endphp

@section('title', 'User Guide Editor')
@section('page-title', $title)
@section('page-subtitle', $isNew ? 'Create a new topic for ' . $localeLabel : $topic['key'] . ' (' . $localeLabel . ')')

@push('styles')
<style>
    .uge-scope { --uge-accent: rgb(var(--primary-rgb, 153, 27, 27)); --uge-accent-dark: rgb(var(--primary-dark-rgb, 127, 29, 29)); --uge-ring: rgba(var(--primary-rgb, 153, 27, 27), 0.18); --uge-tint: rgba(var(--primary-rgb, 153, 27, 27), 0.08); font-family: inherit; }
    .uge-scope input, .uge-scope select, .uge-scope textarea, .uge-scope button { font-family: inherit; }
    .uge-form { display: grid; gap: 20px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1100px) { .uge-form { grid-template-columns: minmax(0, 1fr) 340px; } }
    .uge-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; }
    .uge-card h3 { font-size: 13px; font-weight: 700; color: #111827; margin: 0 0 12px; }
    .uge-field { margin-bottom: 14px; }
    .uge-field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
    .uge-field small { display: block; font-size: 11px; color: #6b7280; margin-top: 4px; line-height: 1.5; }
    .uge-in { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; font-size: 14px; background: #fff; color: #111827; }
    .uge-in:focus { outline: 2px solid var(--uge-ring); border-color: var(--uge-accent); }
    .uge-in[readonly] { background: #f3f4f6; color: #6b7280; }
    textarea.uge-in { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; line-height: 1.55; min-height: 460px; resize: vertical; }
    .uge-count { display: inline-block; min-width: 20px; padding: 0 6px; margin-left: 6px; border-radius: 999px; background: #f3f4f6; color: #6b7280; font-size: 11px; font-weight: 700; text-align: center; }
    .uge-count.on { background: var(--uge-accent); color: #fff; }
    .uge-picker-q { margin-bottom: 6px; }
    .uge-picker-list { max-height: 190px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px; background: #fff; scrollbar-width: thin; }
    .uge-picker-list.is-single { max-height: none; overflow: visible; }
    .uge-opt { display: flex; align-items: flex-start; gap: 8px; padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 13px; color: #1f2937; margin: 0; font-weight: 400; }
    .uge-opt:hover { background: #f9fafb; }
    .uge-opt:has(input:checked) { background: var(--uge-tint); color: #7f1d1d; font-weight: 600; }
    .uge-opt input, .uge-check input { accent-color: var(--uge-accent); margin-top: 3px; flex-shrink: 0; width: 15px; height: 15px; }
    .uge-opt em { display: block; font-style: normal; font-size: 11px; color: #9ca3af; font-weight: 400; }
    .uge-none { padding: 10px; font-size: 12px; color: #9ca3af; text-align: center; }
    .uge-err { color: #b91c1c; font-size: 12px; margin-top: 4px; }
    .uge-errbox { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
    .uge-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.55; }
    .uge-info { background: var(--uge-tint); border: 1px solid #fecaca; color: #7f1d1d; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.55; }
    .uge-tabs { display: inline-flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 8px; }
    .uge-tabs button { padding: 6px 14px; font-size: 12px; background: #fff; color: #4b5563; border: 0; cursor: pointer; }
    .uge-tabs button.is-on { background: var(--uge-accent); color: #fff; font-weight: 600; }
    .uge-preview { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; min-height: 460px; background: #fff; }
    .uge-preview img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
    .uge-preview h2 { font-size: 18px; font-weight: 700; margin: 18px 0 8px; }
    .uge-preview h3 { font-size: 15px; font-weight: 700; margin: 14px 0 6px; }
    .uge-preview p, .uge-preview li { font-size: 14px; line-height: 1.65; }
    .uge-preview ul { list-style: disc; padding-left: 22px; } .uge-preview ol { list-style: decimal; padding-left: 22px; }
    .uge-preview table { border-collapse: collapse; } .uge-preview th, .uge-preview td { border: 1px solid #e5e7eb; padding: 4px 8px; font-size: 13px; }
    .uge-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; border: 0; cursor: pointer; text-decoration: none; }
    .uge-btn.primary { background: var(--uge-accent); color: #fff; } .uge-btn.primary:hover { background: var(--uge-accent-dark); }
    .uge-btn.ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .uge-btn.danger { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
    .uge-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .uge-check { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; color: #374151; }
    .uge-help { font-size: 12px; color: #4b5563; line-height: 1.7; }
    .uge-help code { background: #f3f4f6; border-radius: 4px; padding: 1px 5px; }
</style>
@endpush

@section('content')
<div class="uge-scope">
    @if ($errors->any())
        <div class="uge-errbox"><strong>Not saved.</strong> Fix the points below and try again.</div>
    @endif
    @if ($topic['stale'])
        <div class="uge-warn">The bundled version of this topic has changed since you edited it. Your edited version is what readers see. Review the bundled file, or use <strong>Restore default</strong> to adopt it.</div>
    @endif
    @if ($topic['borrowed'])
        <div class="uge-info">There is no {{ $localeLabel }} version yet. The text below is the English one, as a starting point to translate. Nothing changes until you save.</div>
    @endif

    <div class="uge-form">
        <form method="POST" id="uge-main"
              action="{{ $isNew ? route('admin.user-guide.store') : route('admin.user-guide.update', ['key' => $topic['key']]) }}">
            @csrf
            @unless ($isNew) @method('PUT') @endunless
            <input type="hidden" name="locale" value="{{ $locale }}">

            <div class="uge-card">
                <div class="uge-field">
                    <label for="uge-key">Key</label>
                    <input id="uge-key" class="uge-in" name="key" value="{{ old('key', $topic['key']) }}" {{ $isNew ? '' : 'readonly' }} maxlength="100" placeholder="e.g. my-new-topic">
                    @error('key')<div class="uge-err">{{ $message }}</div>@enderror
                    @if ($isNew)<small>Lowercase letters, digits and hyphens. Used in links like [[my-new-topic]] and cannot be changed later.</small>@endif
                </div>
                <div class="uge-field">
                    <label for="uge-title">Title</label>
                    <input id="uge-title" class="uge-in" name="title" value="{{ old('title', $topic['title']) }}" maxlength="200" required>
                    @error('title')<div class="uge-err">{{ $message }}</div>@enderror
                </div>
                <div class="uge-field">
                    <label for="uge-summary">Summary</label>
                    <input id="uge-summary" class="uge-in" name="summary" value="{{ old('summary', $topic['summary']) }}" maxlength="500">
                    <small>One sentence shown under the title.</small>
                    @error('summary')<div class="uge-err">{{ $message }}</div>@enderror
                </div>

                <div class="uge-field">
                    <label>Content (Markdown)</label>
                    <div class="uge-tabs" role="tablist">
                        <button type="button" class="is-on" data-pane="write">Write</button>
                        <button type="button" data-pane="preview">Preview</button>
                    </div>
                    <textarea id="uge-body" class="uge-in" name="body" required>{{ old('body', $topic['body']) }}</textarea>
                    <div id="uge-preview" class="uge-preview" hidden></div>
                    @error('body')@foreach ($errors->get('body') as $m)<div class="uge-err">{{ $m }}</div>@endforeach @enderror
                </div>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="uge-card">
                <h3>Placement</h3>
                @include('admin.user-guide._picker', ['id' => 'uge-chapter', 'label' => 'Chapter', 'name' => 'chapter', 'single' => true, 'search' => false,
                    'picked' => [old('chapter', $topic['chapter'])],
                    'items' => collect($chapters)->map(fn ($def, $id) => ['value' => $id, 'label' => $def['title'][$locale] ?? $def['title']['en']])->values()->all()])
                @error('chapter')<div class="uge-err" style="margin-top:-8px;margin-bottom:12px">{{ $message }}</div>@enderror
                <div class="uge-field">
                    <label for="uge-order">Order in chapter</label>
                    <input id="uge-order" class="uge-in" type="number" min="0" max="9999" name="order" form="uge-main" value="{{ old('order', $topic['order']) }}">
                    <small>Lower numbers come first.</small>
                    @error('order')<div class="uge-err">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="uge-card">
                <h3>Visible to</h3>
                <small style="display:block;font-size:11px;color:#6b7280;margin-bottom:10px;line-height:1.5">Leave everything unticked to show the topic to all employees. Otherwise an employee needs any one of the ticked choices.</small>
                @include('admin.user-guide._picker', ['id' => 'uge-menus', 'label' => 'Menu access', 'picked' => (array) $pick,
                    'items' => collect($options['menus'])->map(fn ($m) => ['value' => $m->slug, 'label' => $m->name, 'hint' => $m->slug])->all()])
                @include('admin.user-guide._picker', ['id' => 'uge-roles', 'label' => 'Role', 'picked' => (array) $pick,
                    'items' => collect($options['roles'])->map(fn ($r) => ['value' => '@role:' . $r, 'label' => $r])->all()])
                <label class="uge-check">
                    <input type="checkbox" name="requires[]" form="uge-main" value="@approver" @checked($has('@approver'))>
                    <span>Designated approvers</span>
                </label>
                @error('requires')@foreach ($errors->get('requires') as $m)<div class="uge-err">{{ $m }}</div>@endforeach @enderror
            </div>

            <div class="uge-card">
                <div class="uge-actions">
                    <button class="uge-btn primary" type="submit" form="uge-main"><i class="fas fa-save text-xs"></i> Save</button>
                    <a class="uge-btn ghost" href="{{ route('admin.user-guide.index', ['lang' => $locale]) }}">Back</a>
                    @if (!$isNew)
                        <a class="uge-btn ghost" href="{{ route('user-guide.index', ['lang' => $locale]) }}#topic-{{ $topic['key'] }}" target="_blank" rel="noopener">View</a>
                    @endif
                </div>
                @if (!$isNew && $topic['state'] !== 'default')
                    <form id="uge-reset" method="POST" action="{{ route('admin.user-guide.destroy', ['key' => $topic['key']]) }}"
                          style="margin-top:12px" onsubmit="return confirm('{{ $topic['has_file'] ? 'Discard your edits and restore the default version?' : 'Delete this custom topic?' }}')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <button class="uge-btn danger" type="submit">{{ $topic['has_file'] ? 'Restore default' : 'Delete topic' }}</button>
                    </form>
                @endif
            </div>

            <div class="uge-card uge-help">
                <h3>Writing tips</h3>
                Link topics with <code>[[topic-key]]</code>. Use <code>## Heading</code> for sections and <code>&gt; [!NOTE]</code> for call-outs.
                Images are files in <code>public/images/guide</code>: <code>![Alt text](images/guide/name.png)</code>.
                No em dashes, raw HTML or external links. Never put passwords or real customer or employee data in the guide.
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.getElementById('uge-body');
    var box = document.getElementById('uge-preview');
    var tabs = document.querySelectorAll('.uge-tabs button');
    var token = document.querySelector('meta[name="csrf-token"]');

    function show(pane) {
        tabs.forEach(function (b) { b.classList.toggle('is-on', b.dataset.pane === pane); });
        body.hidden = pane === 'preview';
        box.hidden = pane !== 'preview';
        if (pane !== 'preview') { return; }
        box.textContent = 'Loading...';
        fetch(@json(route('admin.user-guide.preview')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: JSON.stringify({ body: body.value, key: document.getElementById('uge-key').value, locale: @json($locale) })
        }).then(function (r) { return r.json(); }).then(function (j) {
            box.innerHTML = j.html || '<em>Nothing to preview.</em>';
        }).catch(function () { box.textContent = 'Preview failed. Try again.'; });
    }
    document.querySelectorAll('.uge-picker').forEach(function (box) {
        var q = box.querySelector('.uge-picker-q') || document.createElement('input'), none = box.querySelector('.uge-none'), count = box.querySelector('[data-count]');
        var opts = box.querySelectorAll('.uge-opt');
        function tally() { if (!count) { return; } var n = box.querySelectorAll('input:checked').length; count.textContent = n; count.classList.toggle('on', n > 0); }
        q.addEventListener('input', function () {
            var term = q.value.trim().toLowerCase(), shown = 0;
            opts.forEach(function (o) { var hit = !term || o.dataset.text.indexOf(term) !== -1; o.hidden = !hit; if (hit) { shown++; } });
            none.hidden = shown > 0;
        });
        box.addEventListener('change', tally);
        tally();
    });
    tabs.forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.pane); }); });
})();
</script>
@endpush
````

### `resources/views/admin/user-guide/_picker.blade.php`

````blade
{{--
    Daftar centang yang bisa dicari, menggantikan <select multiple> bawaan peramban.
    Menulis ke requires[] pada form #uge-main, jadi server tidak tahu bedanya.

    @param string $id       id unik
    @param string $label    judul bagian
    @param array  $items    [['value' => ..., 'label' => ..., 'hint' => ...], ...]
    @param array  $picked   nilai yang sudah terpilih
    @param string $name     nama field (bawaan requires[])
    @param bool   $single   true = pilih satu (radio), tanpa penghitung
    @param bool   $search   tampilkan kolom cari (bawaan true)
--}}
@php
    $name = $name ?? 'requires[]';
    $single = $single ?? false;
    $search = $search ?? true;
@endphp
<div class="uge-field uge-picker" id="{{ $id }}">
    <label for="{{ $id }}-q">{{ $label }}@unless ($single) <span class="uge-count" data-count>0</span>@endunless</label>
    @if ($search)<input id="{{ $id }}-q" type="search" class="uge-in uge-picker-q" placeholder="Search..." autocomplete="off">@endif
    <div class="uge-picker-list {{ $single ? 'is-single' : '' }}">
        @foreach ($items as $item)
            <label class="uge-opt" data-text="{{ strtolower($item['label'] . ' ' . ($item['hint'] ?? '')) }}">
                <input type="{{ $single ? 'radio' : 'checkbox' }}" name="{{ $name }}" form="uge-main" value="{{ $item['value'] }}" @checked(in_array($item['value'], $picked, true))>
                <span>{{ $item['label'] }}@if (!empty($item['hint']))<em>{{ $item['hint'] }}</em>@endif</span>
            </label>
        @endforeach
        <div class="uge-none" hidden>No match.</div>
    </div>
</div>
````

### `resources/guide/_EDITOR.md`

````markdown
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
````

### `tests/Unit/Guide/GuideContentRulesTest.php`

````php
<?php

namespace Tests\Unit\Guide;

use App\Services\Guide\GuideContentRules;
use PHPUnit\Framework\TestCase;

/** Aturan isi topik yang disunting lewat UI (murni, tanpa database). */
class GuideContentRulesTest extends TestCase
{
    private string $imageDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'guide-rules-' . uniqid();
        mkdir($this->imageDir);
        file_put_contents($this->imageDir . '/ok.png', 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->imageDir . '/ok.png');
        @rmdir($this->imageDir);
        parent::tearDown();
    }

    private function rules(): GuideContentRules
    {
        return new GuideContentRules(['other', 'mine'], ['getting-started'], ['rpmo.periods'], ['Delivery RPMO Head'], $this->imageDir);
    }

    private function draft(array $over = []): array
    {
        return $over + ['key' => 'mine', 'chapter' => 'getting-started', 'title' => 'Title', 'summary' => 'S', 'requires' => [], 'body' => "## Hello\n\nText."];
    }

    public function test_a_clean_draft_passes(): void
    {
        $this->assertSame([], $this->rules()->violations($this->draft([
            'requires' => ['rpmo.periods', '@approver', '@role:Delivery RPMO Head'],
            'body'     => "See [[other]].\n\n![Screen](images/guide/ok.png)\n\n<!-- sources: x -->",
        ])));
    }

    public function test_em_and_en_dashes_are_rejected_everywhere(): void
    {
        $dash = "\u{2014}";
        $e = $this->rules()->violations($this->draft(['title' => "A $dash B", 'summary' => "A \u{2013} B", 'body' => "x $dash y"]));
        $this->assertArrayHasKey('title', $e);
        $this->assertArrayHasKey('summary', $e);
        $this->assertArrayHasKey('body', $e);
    }

    public function test_key_chapter_and_required_fields(): void
    {
        $e = $this->rules()->violations($this->draft(['key' => 'Bad Key', 'chapter' => 'nope', 'title' => ' ', 'body' => '']));
        $this->assertSame(['key', 'chapter', 'title', 'body'], array_keys($e));
    }

    public function test_requires_must_reference_known_menus_roles_or_approver(): void
    {
        $e = $this->rules()->violations($this->draft(['requires' => ['ghost.menu', '@role:Nobody', '@approver']]));
        $this->assertCount(2, $e['requires']);
    }

    public function test_raw_html_and_external_links_are_rejected_but_html_comments_are_not(): void
    {
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'x <script>alert(1)</script>'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'x <b>bold</b>'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => '[go](https://example.com)'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => '[go](javascript:alert(1))'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => '[go](//evil.test/x)'])));
        $this->assertSame([], $this->rules()->violations($this->draft(['body' => "ok <!-- note -->"])));
    }

    public function test_images_need_alt_text_an_allowed_extension_and_an_existing_file(): void
    {
        foreach (['![](images/guide/ok.png)', '![a](images/guide/missing.png)', '![a](images/guide/ok.gif)', '![a](https://x.test/a.png)', '![a](images/other/ok.png)', '![a](images/guide/../ok.png)'] as $body) {
            $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => $body])), $body);
        }
    }

    public function test_topic_links_must_exist_and_not_point_to_itself(): void
    {
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'See [[ghost]].'])));
        $this->assertArrayHasKey('body', $this->rules()->violations($this->draft(['body' => 'See [[mine]].'])));
        $this->assertSame([], $this->rules()->violations($this->draft(['body' => 'See [[other|the other topic]].'])));
    }
}
````

### `tests/Unit/Guide/GuideTopicEditorTest.php`

````php
<?php

namespace Tests\Unit\Guide;

use App\Models\UserGuideTopic;
use App\Services\Guide\DatabaseTopicOverrides;
use App\Services\Guide\GuideTopicEditor;
use App\Services\Guide\UserGuideService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Lapisan sunting: timpa topik berkas, topik baru, kembali ke bawaan, status "default berubah", dan efeknya pada UserGuideService. */
class GuideTopicEditorTest extends TestCase
{
    private const KEY = 'sign-in-and-security';

    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_10_28_000001_create_user_guide_topics_table.php'))->up();
        Schema::create('menu', fn ($t) => $t->string('slug'));
        Schema::create('employee_role', fn ($t) => $t->string('name'));
        Cache::flush();
    }

    private function editor(): GuideTopicEditor
    {
        return new GuideTopicEditor($this->service());
    }

    private function service(): UserGuideService
    {
        // Instans baru per panggilan: DatabaseTopicOverrides membaca database sekali per instans.
        return new UserGuideService(new DatabaseTopicOverrides());
    }

    private function data(array $over = []): array
    {
        return $over + ['chapter' => 'getting-started', 'order' => 7, 'title' => 'Edited title', 'summary' => 'Edited summary', 'requires' => [], 'body' => "## Edited\n\nNew text."];
    }

    public function test_an_untouched_topic_is_default_and_comes_from_the_file(): void
    {
        $row = collect($this->editor()->rows('en'))->firstWhere('key', self::KEY);
        $this->assertSame('default', $row['state']);
        $this->assertFalse($row['stale']);
        $this->assertNotSame('Edited title', $this->service()->topics('en')[self::KEY]['title']);
    }

    public function test_saving_overrides_the_file_topic_for_readers_in_that_language_only(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(), 5);

        $en = $this->service()->topics('en')[self::KEY];
        $this->assertSame('Edited title', $en['title']);
        $this->assertStringContainsString('New text.', $en['html']);
        $this->assertSame(7, $en['order']);
        $this->assertNotSame('Edited title', $this->service()->topics('id')[self::KEY]['title']);
        $this->assertSame('edited', collect($this->editor()->rows('en'))->firstWhere('key', self::KEY)['state']);
        $this->assertSame(5, UserGuideTopic::first()->edited_by);
    }

    public function test_a_new_edit_busts_the_cache_even_within_the_same_second(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(['title' => 'First']), null);
        $this->assertSame('First', $this->service()->topics('en')[self::KEY]['title']);

        $this->editor()->save(self::KEY, 'en', $this->data(['title' => 'Second']), null);
        $this->assertSame('Second', $this->service()->topics('en')[self::KEY]['title']);
    }

    public function test_reset_restores_the_file_version(): void
    {
        $original = $this->service()->topics('en')[self::KEY]['title'];
        $this->editor()->save(self::KEY, 'en', $this->data(), null);

        $this->assertTrue($this->editor()->reset(self::KEY, 'en'));
        $this->assertSame($original, $this->service()->topics('en')[self::KEY]['title']);
        $this->assertFalse($this->editor()->reset(self::KEY, 'en'));
    }

    public function test_a_custom_topic_is_added_and_deleting_it_removes_it(): void
    {
        $this->editor()->save('brand-new', 'en', $this->data(['title' => 'Brand new']), null);

        $this->assertArrayHasKey('brand-new', $this->service()->topics('en'));
        $this->assertSame('custom', collect($this->editor()->rows('en'))->firstWhere('key', 'brand-new')['state']);
        $this->assertNull(UserGuideTopic::where('key', 'brand-new')->first()->base_hash);

        $this->editor()->reset('brand-new', 'en');
        $this->assertArrayNotHasKey('brand-new', $this->service()->topics('en'));
    }

    public function test_a_changed_bundled_file_marks_the_edit_as_stale(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(), null);
        $this->assertFalse(collect($this->editor()->rows('en'))->firstWhere('key', self::KEY)['stale']);

        UserGuideTopic::where('key', self::KEY)->update(['base_hash' => str_repeat('0', 32)]);
        $this->assertTrue(collect($this->editor()->rows('en'))->firstWhere('key', self::KEY)['stale']);
        $this->assertTrue($this->editor()->find(self::KEY, 'en')['stale']);
    }

    public function test_the_base_hash_is_captured_once_and_kept_on_later_saves(): void
    {
        $this->editor()->save(self::KEY, 'en', $this->data(), null);
        UserGuideTopic::where('key', self::KEY)->update(['base_hash' => str_repeat('a', 32)]);
        $this->editor()->save(self::KEY, 'en', $this->data(['title' => 'Again']), null);

        $this->assertSame(str_repeat('a', 32), UserGuideTopic::first()->base_hash);
    }

    public function test_a_missing_translation_starts_from_the_english_text_and_is_flagged(): void
    {
        $topic = $this->editor()->find('brand-new-en-only', 'id');
        $this->assertNull($topic);

        $this->editor()->save('only-english', 'en', $this->data(), null);
        $this->assertTrue($this->editor()->find('only-english', 'id') === null || $this->editor()->find('only-english', 'id')['borrowed']);
    }

    public function test_requires_are_stored_and_enforced_by_the_engine(): void
    {
        $this->editor()->save('gated', 'en', $this->data(['requires' => ['@role:Some Role']]), null);

        $topics = $this->service()->topics('en');
        $this->assertArrayNotHasKey('gated', $this->service()->visible($topics, []));
        $this->assertArrayHasKey('gated', $this->service()->visible($topics, [], ['@role:Some Role']));
    }

    public function test_the_engine_still_works_when_the_table_is_missing(): void
    {
        Schema::drop('user_guide_topics');
        Cache::flush();

        $this->assertArrayHasKey(self::KEY, $this->service()->topics('en'));
    }

    public function test_the_preview_renders_images_from_public_and_links_known_topics(): void
    {
        $html = $this->editor()->preview("See [[" . self::KEY . "]].\n\n![Alt](images/guide/sign-in-login.png)", 'x', 'en');

        $this->assertStringContainsString('src="' . asset('images/guide') . '/sign-in-login.png"', $html);
        $this->assertStringContainsString('href="#topic-' . self::KEY . '"', $html);
    }

    public function test_the_content_rules_see_database_topics_and_reference_data(): void
    {
        DB::table('menu')->insert(['slug' => 'rpmo.periods']);
        DB::table('employee_role')->insert(['name' => 'Delivery RPMO Head']);
        $this->editor()->save('custom-one', 'en', $this->data(), null);

        $ok = $this->editor()->rules()->violations([
            'key' => 'x', 'chapter' => 'getting-started', 'title' => 'T', 'summary' => '', 'requires' => ['rpmo.periods', '@role:Delivery RPMO Head'],
            'body' => 'See [[custom-one]] and [[' . self::KEY . ']].',
        ]);
        $this->assertSame([], $ok);
        $this->assertTrue($this->editor()->keyExists('custom-one'));
        $this->assertTrue($this->editor()->keyExists(self::KEY));
        $this->assertFalse($this->editor()->keyExists('never-used'));
    }
}
````

## Lampiran B. Referensi (bukan untuk disalin begitu saja)

### B.1 `config/user_guide.php` di branch eco_config

Referensi isi `locales`, `chapters` dan teks `ui`. Config HRGA yang ada tetap dipakai. Hanya pastikan id bab yang Anda butuhkan tersedia.

````php
<?php

/**
 * User Guide: bab (chapter) panduan pengguna. Isi panduan = berkas Markdown di resources/guide/{en,id}/*.md
 * (lihat resources/guide/_AUTHORING.md). Bab hanya mengelompokkan topik; topik yang tak boleh dilihat pengguna
 * tidak dikirim sama sekali, dan bab tanpa topik terlihat tidak tampil.
 *
 * Urutan bab = urutan di sini. `title` per bahasa; istilah menu/tombol di dalamnya tetap bahasa Inggris (sama dengan layar aplikasi).
 */
return [
    'locales' => ['en' => 'English', 'id' => 'Bahasa Indonesia'],
    'default_locale' => 'en',

    'chapters' => [
        'getting-started' => ['icon' => 'compass',    'title' => ['en' => 'Getting started',           'id' => 'Memulai']],
        'my-workspace'    => ['icon' => 'smart-home', 'title' => ['en' => 'My Workspace',              'id' => 'My Workspace']],
        'human-capital'   => ['icon' => 'address-book', 'title' => ['en' => 'Human Capital',           'id' => 'Human Capital']],
        'external'        => ['icon' => 'briefcase',  'title' => ['en' => 'External consultants',      'id' => 'Konsultan External']],
        'finance'         => ['icon' => 'wallet',     'title' => ['en' => 'Finance',                   'id' => 'Finance']],
        'work-service'    => ['icon' => 'tools',      'title' => ['en' => 'Work & Service',            'id' => 'Work & Service']],
        'business-legal'  => ['icon' => 'building-skyscraper', 'title' => ['en' => 'Business & Legal', 'id' => 'Business & Legal']],
        'reporting'       => ['icon' => 'chart-histogram', 'title' => ['en' => 'Reporting',            'id' => 'Reporting']],
        'administration'  => ['icon' => 'shield-cog', 'title' => ['en' => 'Administration',            'id' => 'Administration']],
        'reference'       => ['icon' => 'book',       'title' => ['en' => 'Workflows & reference',     'id' => 'Alur kerja & referensi']],
    ],

    // Teks tetap pada tampilan (bukan isi panduan).
    'ui' => [
        'en' => [
            'title' => 'User Guide', 'subtitle' => 'Step-by-step help for the features you can use, in the right order.',
            'toc' => 'Table of Contents', 'toc_hint' => 'Search, or pick a chapter and topic to jump straight to it.', 'expand_all' => 'Expand all', 'collapse_all' => 'Collapse all', 'chapter' => 'Chapter', 'topic_count' => 'topics', 'search_toc' => 'Search the guide…', 'no_results' => 'Nothing found. Try another word.',
            'results' => 'results', 'download_pdf' => 'Download PDF', 'updated' => 'Updated', 'language' => 'Language', 'tailored' => 'This guide is tailored to your access',
            'topics_of' => 'topics', 'on_this_page' => 'On this topic', 'back_top' => 'Back to top',
            'untranslated' => 'This topic is not available in your language yet; the English version is shown.', 'related' => 'Related topics', 'empty' => 'No topics are available for your access yet.',
            'note' => 'Note', 'tip' => 'Tip', 'important' => 'Important', 'warning' => 'Warning', 'topic_updated' => 'Topic updated', 'print' => 'Print', 'cover_for' => 'Prepared for',
        ],
        'id' => [
            'title' => 'User Guide', 'subtitle' => 'Panduan langkah demi langkah untuk fitur yang dapat Anda gunakan, sesuai urutan kerja yang benar.',
            'toc' => 'Daftar Isi', 'toc_hint' => 'Cari, atau pilih bab dan topik untuk langsung menuju ke sana.', 'expand_all' => 'Buka semua', 'collapse_all' => 'Tutup semua', 'chapter' => 'Bab', 'topic_count' => 'topik', 'search_toc' => 'Cari di panduan…', 'no_results' => 'Tidak ditemukan. Coba kata lain.',
            'results' => 'hasil', 'download_pdf' => 'Unduh PDF', 'updated' => 'Diperbarui', 'language' => 'Bahasa', 'tailored' => 'Panduan ini disesuaikan dengan akses Anda',
            'topics_of' => 'topik', 'on_this_page' => 'Di topik ini', 'back_top' => 'Kembali ke atas',
            'untranslated' => 'Topik ini belum tersedia dalam bahasa Anda; versi bahasa Inggris ditampilkan.', 'related' => 'Topik terkait', 'empty' => 'Belum ada topik yang tersedia untuk akses Anda.',
            'note' => 'Catatan', 'tip' => 'Tips', 'important' => 'Penting', 'warning' => 'Peringatan', 'topic_updated' => 'Topik diperbarui', 'print' => 'Cetak', 'cover_for' => 'Disiapkan untuk',
        ],
    ],
];
````

### B.2 `app/Services/Guide/UserGuideService.php` setelah perubahan editor

Ini versi lengkap file bersama di branch `eco_config`. Tiga titik perubahan editor sama dengan bagian 4.1. Bagian lain adalah mesin bawaan paket, jadi bila file HRGA Anda sudah berbeda, **terapkan hanya tiga titik itu**, jangan menimpa file.

````php
<?php

namespace App\Services\Guide;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * User Guide: membaca topik Markdown (resources/guide/{locale}/*.md), menyaring menurut izin menu pengguna,
 * dan menyajikannya sebagai HTML + daftar isi + indeks pencarian. MURNI file + cache; tidak menulis apa pun.
 *
 * Satu berkas = satu topik. Awal berkas berisi front matter YAML:
 *   key, chapter, order, title, summary, requires (daftar slug menu; kosong = semua pengguna; cukup salah satu), updated (Y-m-d).
 * Tanggal "Updated" di halaman = `updated` terbaru dari topik yang tampil (bukan waktu berkas, yang berubah saat deploy).
 */
final class UserGuideService
{
    public const DIR = 'guide';
    private const CACHE_VER = 'v1';

    /** Sumber tambahan (hasil sunting admin) di atas berkas; null = hanya berkas. */
    public function __construct(private readonly ?TopicOverrides $overrides = null)
    {
    }

    /** Bahasa yang dipakai: ?lang= (sah) → preferensi pengguna (sah) → bawaan. */
    public function resolveLocale(?string $requested, ?string $preference): string
    {
        $allowed = array_keys(config('user_guide.locales'));
        foreach ([$requested, $preference] as $cand) {
            if (is_string($cand) && in_array($cand, $allowed, true)) {
                return $cand;
            }
        }

        return (string) config('user_guide.default_locale', 'en');
    }

    /**
     * Semua topik satu bahasa, diurutkan menurut bab lalu `order`. Topik yang belum ada terjemahannya diisi dari bahasa Inggris
     * (`fallback` = true) supaya pengguna tidak kehilangan topik.
     *
     * @return array<string,array<string,mixed>> key => topik
     */
    public function topics(string $locale): array
    {
        $sig = $this->signature($locale);

        return Cache::remember('user_guide:' . self::CACHE_VER . ":{$locale}:{$sig}", now()->addHours(12), function () use ($locale) {
            $base = $this->load('en', false);
            $mine = $locale === 'en' ? [] : $this->load($locale, false);
            $titleMap = [];
            foreach ($base as $k => $t) {
                $titleMap[$k] = $mine[$k]['title'] ?? $t['title'];
            }
            $out = [];
            foreach ($base as $key => $en) {
                $t = $mine[$key] ?? $en + ['fallback' => $locale !== 'en'];
                $t['fallback'] = $locale !== 'en' && !isset($mine[$key]);
                $out[$key] = $t;
            }
            // Topik yang hanya ada di bahasa non-Inggris tetap ikut.
            foreach ($mine as $key => $t) {
                $out[$key] ??= $t + ['fallback' => false];
            }
            foreach ($out as $key => &$t) {
                $built = $this->render($t['body'], $key, $locale, $titleMap);
                $t['html'] = $built['html'];
                $t['sections'] = $built['sections'];
                $t['headings'] = $built['headings'];
                $t['text'] = $built['text'];
                unset($t['body']);
            }
            unset($t);

            $chapterOrder = array_flip(array_keys(config('user_guide.chapters')));
            uasort($out, fn ($a, $b) => [$chapterOrder[$a['chapter']] ?? 99, $a['order'], $a['key']] <=> [$chapterOrder[$b['chapter']] ?? 99, $b['order'], $b['key']]);

            return $out;
        });
    }

    /**
     * Topik yang boleh dilihat: tanpa `requires` = semua orang; selain itu cukup SATU izin yang dimiliki. Tidak ada pengecualian
     * (tak ada "lihat semua"): siapa pun, termasuk administrator, hanya menerima topik sesuai izin menunya sendiri.
     *
     * @param  array<string,array<string,mixed>>  $topics
     * @param  string[]  $permSlugs
     * @param  string[]  $extra  izin semu yang dimiliki pengguna (mis. "@approver" = ditunjuk sebagai penyetuju di alur persetujuan,
     *                           sehingga menu My Approvals tampil walau tanpa izin menu)
     * @return array<string,array<string,mixed>>
     */
    public function visible(array $topics, array $permSlugs, array $extra = []): array
    {
        $have = array_flip(array_merge($permSlugs, $extra));

        return array_filter($topics, function ($t) use ($have) {
            if (empty($t['requires'])) {
                return true;
            }
            foreach ($t['requires'] as $slug) {
                if (isset($have[$slug])) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Topik dikelompokkan per bab (bab kosong dibuang).
     *
     * @param  array<string,array<string,mixed>>  $visible
     * @return array<string,array{id:string,icon:string,title:string,topics:array<string,array<string,mixed>>}>
     */
    public function chapters(array $visible, string $locale): array
    {
        $out = [];
        $n = 0;
        foreach (config('user_guide.chapters') as $id => $def) {
            $topics = array_filter($visible, fn ($t) => $t['chapter'] === $id);
            if ($topics) {
                $n++;
                // Penomoran mengikuti yang TAMPIL bagi pengguna ini (1, 1.1, 1.2 …), sama di daftar isi, isi, dan PDF.
                $i = 0;
                foreach ($topics as $key => &$t) {
                    $t['no'] = $n . '.' . (++$i);
                }
                unset($t);
                $out[$id] = ['id' => $id, 'no' => (string) $n, 'icon' => $def['icon'], 'title' => $def['title'][$locale] ?? $def['title']['en'], 'topics' => $topics];
            }
        }

        return $out;
    }

    /** Tanggal `updated` terbaru (Y-m-d) dari topik yang ditampilkan. */
    public function lastUpdated(array $visible): ?string
    {
        $dates = array_filter(array_column($visible, 'updated'));

        return $dates ? max($dates) : null;
    }

    /**
     * Indeks pencarian ringan (dikirim ke peramban): satu entri per bagian (h2) tiap topik yang boleh dilihat.
     *
     * @return array<int,array{t:string,k:string,c:string,h:string,a:string,x:string}>
     */
    public function searchIndex(array $visible, array $chapters): array
    {
        $titles = [];
        foreach ($chapters as $c) {
            foreach ($c['topics'] as $k => $_) {
                $titles[$k] = $c['title'];
            }
        }
        $out = [];
        foreach ($visible as $key => $t) {
            foreach ($t['sections'] as $s) {
                $out[] = ['t' => $t['title'], 'k' => $key, 'c' => $titles[$key] ?? '', 'h' => $s['heading'], 'a' => $s['id'], 'x' => $s['text']];
            }
        }

        return $out;
    }

    /** Berkas panduan yang ada (untuk pemeriksa dan tanda tangan cache). @return string[] */
    public function files(string $locale): array
    {
        $dir = resource_path(self::DIR . '/' . $locale);

        return is_dir($dir) ? array_values(array_filter(glob($dir . '/*.md') ?: [], fn ($f) => !str_starts_with(basename($f), '_'))) : [];
    }

    /**
     * Topik satu bahasa tanpa render: berkas bawaan, lalu ditimpa/ditambah hasil sunting bila ada.
     *
     * @return array<string,array<string,mixed>>
     */
    public function load(string $locale, bool $strict): array
    {
        return ($this->overrides?->forLocale($locale) ?? []) + $this->loadFiles($locale, $strict);
    }

    /** Hanya berkas bawaan (tanpa hasil sunting). Dipakai pemeriksa dan editor. @return array<string,array<string,mixed>> */
    public function loadFiles(string $locale, bool $strict = false): array
    {
        $out = [];
        foreach ($this->files($locale) as $file) {
            $parsed = $this->parseFile($file);
            if ($parsed === null) {
                if ($strict) {
                    throw new \RuntimeException('Front matter tidak valid: ' . basename($file));
                }
                continue;
            }
            $out[$parsed['key']] = $parsed + ['file' => basename($file)];
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function parseFile(string $file): ?array
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));
        if (!preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $raw, $m)) {
            return null;
        }
        $meta = $this->parseFrontMatter($m[1]);
        if (!is_array($meta) || empty($meta['key']) || empty($meta['chapter']) || empty($meta['title'])) {
            return null;
        }
        $updated = $meta['updated'] ?? null;
        if (is_int($updated)) {
            $updated = date('Y-m-d', $updated);
        }

        return [
            'key'      => (string) $meta['key'],
            'chapter'  => (string) $meta['chapter'],
            'order'    => (int) ($meta['order'] ?? 100),
            'title'    => (string) $meta['title'],
            'summary'  => (string) ($meta['summary'] ?? ''),
            'requires' => array_values(array_map('strval', (array) ($meta['requires'] ?? []))),
            'updated'  => is_string($updated) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $updated) ? $updated : null,
            'body'     => trim($m[2]),
        ];
    }

    /**
     * Front matter sederhana (tanpa paket YAML, karena symfony/yaml hanya ada di lingkungan pengembangan, tidak di produksi):
     * baris `kunci: nilai`; nilai berupa teks (boleh diberi tanda kutip), angka, atau daftar sebaris `[a, "b"]`.
     * Komentar ` # …` di akhir baris diabaikan.
     *
     * @return array<string,mixed>
     */
    public function parseFrontMatter(string $text): array
    {
        $out = [];
        foreach (preg_split('/\n/', $text) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#') || !preg_match('/^([A-Za-z_][A-Za-z0-9_\-]*)\s*:\s*(.*)$/', $line, $m)) {
                continue;
            }
            $out[$m[1]] = $this->scalar(trim($m[2]));
        }

        return $out;
    }

    /** @return string|int|array<int,string> */
    private function scalar(string $v)
    {
        if ($v === '') {
            return '';
        }
        if ($v[0] === '[') {
            $end = strrpos($v, ']');
            $inner = trim(substr($v, 1, ($end === false ? strlen($v) : $end) - 1));
            if ($inner === '') {
                return [];
            }
            $items = [];
            preg_match_all('/\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'|([^,]+))/', $inner, $mm, PREG_SET_ORDER);
            foreach ($mm as $x) {
                $items[] = trim(($x[1] ?? '') !== '' ? $x[1] : (($x[2] ?? '') !== '' ? $x[2] : ($x[3] ?? '')));
            }

            return array_values(array_filter($items, fn ($i) => $i !== ''));
        }
        if ($v[0] === '"' || $v[0] === "'") {
            $close = strpos($v, $v[0], 1);

            return $close === false ? substr($v, 1) : stripcslashes(substr($v, 1, $close - 1));
        }
        $v = preg_replace('/\s+#.*$/', '', $v);
        if (preg_match('/^-?\d+$/', $v)) {
            return (int) $v;
        }

        return trim($v);
    }

    // ── render ────────────────────────────────────────────────────────────

    /**
     * Markdown → HTML. Tambahan: tautan antartopik [[key]] / [[key|teks]], kotak catatan `> [!NOTE|TIP|IMPORTANT|WARNING]`,
     * id pada judul (key--slug), tabel dibungkus agar bisa digulir di layar sempit.
     *
     * @param  array<string,string>  $titleMap  key → judul (bahasa pengguna)
     * @return array{html:string,sections:array<int,array{id:string,heading:string,text:string}>,headings:array<int,array{id:string,text:string,level:int}>,text:string}
     */
    public function render(string $markdown, string $key, string $locale, array $titleMap = []): array
    {
        $markdown = preg_replace_callback('/\[\[([a-z0-9\-]+)(?:\|([^\]]+))?\]\]/', function ($m) use ($titleMap) {
            $label = $m[2] ?? ($titleMap[$m[1]] ?? $m[1]);

            return isset($titleMap[$m[1]]) ? '[' . $label . '](#topic-' . $m[1] . ')' : $label;
        }, $markdown);

        $env = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new TableExtension());
        $env->addExtension(new StrikethroughExtension());
        $html = (new MarkdownConverter($env))->convert($markdown)->getContent();

        $ui = config("user_guide.ui.{$locale}", config('user_guide.ui.en'));
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $dom->getElementById('__root');
        $xp = new \DOMXPath($dom);

        // Kotak catatan
        foreach (iterator_to_array($xp->query('//blockquote')) as $bq) {
            $first = $xp->query('./p[1]', $bq)->item(0);
            if (!$first || !preg_match('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING)\]\s*/i', $first->textContent, $mm)) {
                continue;
            }
            $type = strtolower($mm[1]);
            // buang penanda dari teks (simpul teks pertama)
            $firstText = $first->firstChild;
            if ($firstText && $firstText->nodeType === XML_TEXT_NODE) {
                $firstText->nodeValue = preg_replace('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING)\]\s*/i', '', $firstText->nodeValue);
            }
            $box = $dom->createElement('div');
            $box->setAttribute('class', "callout callout-{$type}");
            $title = $dom->createElement('p');
            $title->appendChild($dom->createTextNode($ui[$type] ?? ucfirst($type)));
            $title->setAttribute('class', 'callout-title');
            $box->appendChild($title);
            while ($bq->firstChild) {
                $box->appendChild($bq->firstChild);
            }
            $bq->parentNode->replaceChild($box, $bq);
        }

        // Tabel dibungkus
        foreach (iterator_to_array($xp->query('//table')) as $tb) {
            $wrap = $dom->createElement('div');
            $wrap->setAttribute('class', 'g-table');
            $tb->parentNode->replaceChild($wrap, $tb);
            $wrap->appendChild($tb);
        }

        // Judul: h1 → h2; beri id
        $used = [];
        $headings = [];
        foreach (iterator_to_array($xp->query('//h1|//h2|//h3')) as $h) {
            $level = $h->nodeName === 'h3' ? 3 : 2;
            if ($h->nodeName === 'h1') {
                $new = $dom->createElement('h2');
                while ($h->firstChild) {
                    $new->appendChild($h->firstChild);
                }
                $h->parentNode->replaceChild($new, $h);
                $h = $new;
            }
            $text = trim($h->textContent);
            $slug = Str::slug($text) ?: 'section';
            $id = "{$key}--{$slug}";
            for ($i = 2; isset($used[$id]); $i++) {
                $id = "{$key}--{$slug}-{$i}";
            }
            $used[$id] = true;
            $h->setAttribute('id', $id);
            $headings[] = ['id' => $id, 'text' => $text, 'level' => $level];
        }

        // Bagian (per h2) untuk pencarian
        $sections = [];
        $cur = ['id' => "topic-{$key}", 'heading' => '', 'text' => ''];
        foreach ($root->childNodes as $node) {
            if ($node->nodeName === 'h2') {
                if (trim($cur['text']) !== '' || $cur['heading'] !== '') {
                    $sections[] = $cur;
                }
                $cur = ['id' => $node->getAttribute('id'), 'heading' => trim($node->textContent), 'text' => ''];
                continue;
            }
            $cur['text'] .= ' ' . preg_replace('/\s+/u', ' ', $node->textContent);
        }
        $sections[] = $cur;
        foreach ($sections as &$s) {
            $s['text'] = trim(preg_replace('/\s+/u', ' ', $s['text']));
        }
        unset($s);

        $inner = '';
        foreach ($root->childNodes as $node) {
            $inner .= $dom->saveHTML($node);
        }

        return ['html' => $inner, 'sections' => $sections, 'headings' => $headings, 'text' => trim(preg_replace('/\s+/u', ' ', $root->textContent))];
    }

    private function signature(string $locale): string
    {
        $parts = [];
        foreach (array_unique(['en', $locale]) as $loc) {
            foreach ($this->files($loc) as $f) {
                $parts[] = $f . '|' . filemtime($f) . '|' . filesize($f);
            }
        }
        $parts[] = (string) filemtime(config_path('user_guide.php'));
        $parts[] = $this->overrides?->version() ?? '';

        return md5(implode("\n", $parts));
    }
}
````

### B.3 Penyimpangan lain di branch eco_config (opsional)

Hal berikut ada di branch `eco_config`, tidak dibutuhkan editor, dan boleh diadopsi HRGA belakangan.

**Izin semu `@role:Nama Role`** di `UserGuideController::data()`, tepat sebelum `$visible = $this->guide->visible(...)`:

````php
            // Izin semu per role: "@role:Nama Role" dihitung dari SEMUA role yang dipegang karyawan (bisa lebih dari satu).
            try {
                $roleNames = DB::table('employee_role_assignment as a')
                    ->join('employee_role as r', 'r.id', '=', 'a.role_id')
                    ->where('a.employee_id', (int) session('user.id'))
                    ->pluck('r.name');
                foreach ($roleNames as $name) {
                    $extra[] = '@role:' . $name;
                }
            } catch (\Throwable) { /* tanpa izin semu role, topik tetap disaring menurut izin menu */ }
````

Perlu `use Illuminate\Support\Facades\DB;` di controller. `GuideContentRules` sudah menerima `@role:` pada `requires`, jadi editor tidak berubah saat Anda mengadopsinya.

**Gambar lokal pada topik**: aturan paket asli melarang gambar. Di `eco_config` diizinkan `images/guide/*.png|jpg` yang ada dan ber-alt. Perubahannya ada di `docs/HRGA/tools/guide-check.php`, `UserGuideController::inlineImages()` (gambar disisipkan sebagai data URI agar tampil di PDF) dan CSS `.guide-body img` pada tampilan. `GuideContentRules` mengikuti aturan gambar ini. Bila HRGA tetap melarang gambar, ubah `GuideContentRules::bodyProblems()` dan `guide-check.php` bersamaan.

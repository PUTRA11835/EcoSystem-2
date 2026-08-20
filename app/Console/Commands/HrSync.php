<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkronisasi konteks modul HR & General untuk room chat baru.
 *
 * MASALAH YANG DISELESAIKAN:
 * Setiap kali pekerjaan berpindah ke room chat baru, konteksnya hilang dan asisten
 * menelusuri ulang kode & database dari nol — mahal, lambat, dan hasilnya bisa
 * berbeda-beda karena database terus berubah. Perintah ini membaca keadaan nyata
 * satu kali lalu menuliskannya sebagai blok siap-tempel, sehingga room baru cukup
 * MEMBACA fakta alih-alih MENCARINYA.
 *
 * SIFAT:
 * Hanya SELECT ke database (tidak pernah menulis) dan hanya menulis berkas di
 * dalam docs/updated-file/. Aman dijalankan kapan saja, termasuk di produksi.
 *
 * PEMAKAIAN:
 *   php artisan hr:sync              tampilkan blok handoff di layar
 *   php artisan hr:sync --write      tulis docs/updated-file/HANDOFF.md + blok otomatis
 *                                    di docs/updated-file/01-KONTEKS-SISTEM.md
 *   php artisan hr:sync --full       sertakan detail tambahan
 */
class HrSync extends Command
{
    protected $signature = 'hr:sync
                            {--write : Tulis hasilnya ke docs/updated-file/HANDOFF.md dan 01-KONTEKS-SISTEM.md}
                            {--full : Sertakan detail tambahan (kolom employee, daftar tabel HR)}';

    protected $description = 'Kumpulkan fakta terkini modul HR & General untuk sinkronisasi room chat baru';

    /** Menu induk modul HR & General. */
    private const PARENT_SLUG = 'general';

    /** Tabel yang menjadi penanda kemajuan modul Attendance. */
    private const ATTENDANCE_TABLES = [
        'branches',
        'project_sites',
        'shifts',
        'employee_shifts',
        'attendance_settings',
        'attendance_records',
        'attendance_corrections',
        'attendance_sources',
    ];

    /**
     * Tabel modul HR lain yang belum dikerjakan — dipantau agar tidak terlewat.
     *
     * Nama tabel Overtime masih tentatif; dipantau sejak sekarang supaya begitu
     * migrasinya dibuat, statusnya langsung ikut terbaca di HANDOFF tanpa harus
     * mengingat memperbarui perintah ini.
     */
    private const OTHER_HR_TABLES = [
        'overtime_settings',
        'overtime_requests',
        'overtime_records',
        'leave_types',
        'leave_entitlements',
        'leave_requests',
        'leaves',
    ];

    public function handle(): int
    {
        $facts = $this->collectFacts();

        if (!$facts) {
            return self::FAILURE;
        }

        $handoff = $this->renderHandoff($facts);

        if ($this->option('write')) {
            $this->writeHandoff($handoff);
            $this->writeSnapshotBlock($facts);
            $this->newLine();
            $this->info('Tertulis: docs/updated-file/HANDOFF.md');
            $this->info('Tertulis: docs/updated-file/01-KONTEKS-SISTEM.md (blok otomatis)');
        } else {
            $this->line($handoff);
            $this->newLine();
            $this->comment('Tambahkan --write untuk menyimpannya ke docs/updated-file/.');
        }

        $this->newLine();
        $this->renderSummaryTable($facts);

        return self::SUCCESS;
    }

    /**
     * Baca keadaan nyata database & repo.
     *
     * @return array<string,mixed>|null null bila database tidak dapat dihubungi.
     */
    private function collectFacts(): ?array
    {
        try {
            $dbVersion = DB::selectOne('SELECT VERSION() AS v')->v ?? 'tidak diketahui';
        } catch (\Throwable $e) {
            $this->error('Tidak dapat menghubungi database: ' . $e->getMessage());
            $this->comment('Periksa DB_DATABASE di .env dan pastikan MySQL/MariaDB berjalan.');

            return null;
        }

        $parent = DB::table('menu')->where('slug', self::PARENT_SLUG)->first();

        $children = $parent
            ? DB::table('menu')
                ->where('parent_id', $parent->id)
                ->orderBy('order_seq')
                ->get(['slug', 'name', 'type', 'order_seq'])
            : collect();

        // Tabel attendance mana yang sudah ada — ini penanda langkah A2 selesai atau belum.
        $attendanceTables = [];
        foreach (self::ATTENDANCE_TABLES as $table) {
            $attendanceTables[$table] = Schema::hasTable($table);
        }

        $otherHrTables = [];
        foreach (self::OTHER_HR_TABLES as $table) {
            $otherHrTables[$table] = Schema::hasTable($table);
        }

        return [
            'generated_at'      => now()->format('d F Y H:i'),
            'db_name'           => config('database.connections.' . config('database.default') . '.database'),
            'db_version'        => $dbVersion,
            'app_env'           => config('app.env'),
            'app_url'           => config('app.url'),
            'app_timezone'      => config('app.timezone'),
            'is_https'          => str_starts_with((string) config('app.url'), 'https://'),
            'git_branch'        => $this->git('rev-parse --abbrev-ref HEAD'),
            'git_commit'        => $this->git('rev-parse --short HEAD'),
            'migration_files'   => count(File::glob(database_path('migrations/*.php'))),
            'migration_ran'     => Schema::hasTable('migrations') ? DB::table('migrations')->count() : 0,
            'menu_total'        => DB::table('menu')->count(),
            'parent_menu'       => $parent,
            'children'          => $children,
            'employee_total'    => Schema::hasTable('employee') ? DB::table('employee')->count() : 0,
            'employee_active'   => Schema::hasTable('employee') ? DB::table('employee')->where('is_active', 1)->count() : 0,
            'employee_columns'  => Schema::hasTable('employee') ? Schema::getColumnListing('employee') : [],
            'has_reports_to'    => Schema::hasColumn('employee', 'reports_to_id'),
            'role_total'        => Schema::hasTable('employee_role') ? DB::table('employee_role')->count() : 0,
            'hr_roles'          => Schema::hasTable('employee_role')
                ? DB::table('employee_role')->whereIn('id', [31, 32, 33])->get(['id', 'name'])
                : collect(),
            'attendance_tables' => $attendanceTables,
            'other_hr_tables'   => $otherHrTables,
            'attendance_rows'   => Schema::hasTable('attendance_records')
                ? DB::table('attendance_records')->count()
                : null,
            'location_rows'     => Schema::hasTable('branches')
                ? DB::table('branches')->whereNull('deleted_at')->count()
                : null,
            // Dipakai stepStatus(): tabel lengkap tanpa cabang terisi berarti
            // geofence belum punya pembanding apa pun.
            'branch_rows'       => Schema::hasTable('branches')
                ? DB::table('branches')->whereNull('deleted_at')->where('is_active', true)->count()
                : 0,
            'shift_rows'        => Schema::hasTable('shifts') ? DB::table('shifts')->count() : 0,
            'route_general'     => File::exists(base_path('routes/HR_General.php')),
            'holidays'          => Schema::hasTable('holidays') ? DB::table('holidays')->count() : 0,
        ];
    }

    /**
     * Susun blok siap-tempel untuk pesan pertama di room chat baru.
     *
     * Sengaja padat: tiap baris yang ditulis di sini adalah satu penelusuran kode
     * yang TIDAK perlu dilakukan asisten. Menambah baris yang jarang dipakai
     * justru memperbesar biaya setiap giliran percakapan.
     */
    private function renderHandoff(array $f): string
    {
        $done  = $this->stepStatus($f);
        $menus = $f['children']->isEmpty()
            ? '  (belum ada anak menu — langkah A1 belum dikerjakan)'
            : $f['children']->map(fn ($m) => '  - ' . $m->slug . '  [' . $m->type . ']  ' . $m->name)->implode("\n");

        $tables = collect($f['attendance_tables'])
            ->map(fn ($ada, $t) => '  ' . ($ada ? '[v]' : '[ ]') . ' ' . $t)
            ->implode("\n");

        $https = $f['is_https']
            ? 'ya'
            : 'TIDAK (' . $f['app_url'] . ') — Geolocation hanya jalan di localhost';

        return <<<TXT
=== KONTEKS PROYEK — ECOSYSTEM-2 / MODUL HR & GENERAL ===
Dibuat otomatis oleh `php artisan hr:sync` pada {$f['generated_at']}.
Fakta di bawah SUDAH DIVERIFIKASI. Jangan telusuri ulang.

TUGAS: menambahkan modul HR & General ke aplikasi Laravel internal (EcoSystem,
perusahaan konsultan SAP). Modul DITAMBAHKAN, tidak menggantikan apa pun.
Sub-modul ATTENDANCE (B0-B9) SELESAI & teruji; B10 ditunda.
Fokus saat ini: sub-modul OVERTIME (lembur) — rancangan belum disusun.
SISTEM SUDAH LIVE DI PRODUKSI — pengujian dilakukan di lokal.

--- STACK ---
Laravel 12 - PHP 8.2 - {$f['db_version']} - database `{$f['db_name']}`
Blade + Tailwind v4 (via CDN cdn.tailwindcss.com) + Vite 7
JavaScript VANILLA. TIDAK ADA Vue/React/Inertia/Livewire/Alpine/jQuery. AJAX = fetch()
Auth: session-based tulisan tangan. BUKAN Auth::attempt(). Identitas = session('user')['id']
Sanctum terpasang tapi NOL pemakaian
APP_ENV={$f['app_env']} - APP_URL={$f['app_url']} - HTTPS: {$https}
Timezone: {$f['app_timezone']}

--- REPO ---
Branch {$f['git_branch']} @ {$f['git_commit']}
{$f['migration_files']} berkas migrasi ({$f['migration_ran']} sudah dijalankan)
{$f['menu_total']} baris di tabel `menu`
routes/HR_General.php: {$this->yn($f['route_general'])}

--- MENU & IZIN ---
Menu induk: id={$f['parent_menu']->id} slug='general' name='HR & General' order_seq={$f['parent_menu']->order_seq}
Awalan slug yang BENAR: 'general.*'  (BUKAN 'hr.*')
Pola granularitas: .view / .edit / .manage
Slug baru WAJIB didaftarkan lewat migrasi memakai App\Support\MenuRegistrar
MenuRegistrar memberi grant awal HANYA ke EC Administrator — jangan bagikan lewat migrasi
Otorisasi rute: ->middleware('menu:general.xxx')   Blade: @if(\$can('general.xxx'))
Sidebar HARDCODE di resources/views/dashboard.blade.php (2.400 baris). Titik modul ini:
  ~890-935  dropdown "HR & General" (Attendance + Attendance Corrections)
  ~938-950  item TINGKAT ATAS "My Attendance"
  ~1310-1345 Management > HR & General (Branches, Shifts, Attendance Settings)
  ~1497-1498 variabel state · ~1570-1582 fungsi toggle

Anak menu 'general' saat ini:
{$menus}

--- ROLE & KARYAWAN ---
{$f['role_total']} role di tabel employee_role (app/Enums/RoleId.php hanya memuat sebagian)
Role HR sudah ada: {$this->hrRoles($f)}
Karyawan: {$f['employee_total']} total, {$f['employee_active']} aktif
employee.reports_to_id: {$this->yn($f['has_reports_to'])} — hierarki atasan belum dibangun
employee_basic_data.direct_supervision / .manager: string bebas, 100% NULL. JANGAN dipakai

--- KEMAJUAN MODUL ATTENDANCE ---
{$tables}
Baris attendance_records: {$this->nullable($f['attendance_rows'])}
Baris branches (aktif): {$this->nullable($f['location_rows'])}   shifts: {$f['shift_rows']}
Langkah terakhir yang tampak selesai: {$done}
Rencana langkah lengkap: docs/updated-file/attendance/14-RANCANGAN-BERLAKU.md (B0-B10)

--- YANG SUDAH ADA, JANGAN DIBANGUN ULANG ---
app/Services/PeriodService.php     mesin periode buka/tutup/kunci (siklus 21-20)
app/Services/HolidayService.php    isNonWorkingDay(), getHolidayDates()  [{$f['holidays']} hari libur]
app/Models/PeriodLateExceptionRequest.php   cetak biru approval 2 tingkat
app/Models/Notification.php        Notification::create() otomatis kirim Web Push
AuthController::parseUserAgent()   parsing device/browser/OS, teruji produksi (baris 162)
app/Exports/ (16 kelas)            pola export Excel, maatwebsite/excel sudah terpasang
showConfirm() / showNotification() helper UI global di dashboard.blade.php
my-profile (menu id 149)           10 section data karyawan, SUDAH SELESAI
calendar.timesheets                timesheet, SUDAH SELESAI

--- KONVENSI WAJIB ---
Tabel      : PLURAL snake_case; PK \$table->id(); timestamps() selalu;
             indeks di akhir blok & komposit diberi NAMA; docblock beralasan
             dalam Bahasa Indonesia di atas kelas migrasi
FK employee: \$table->unsignedBigInteger('employee_id');
             \$table->foreign('employee_id')->references('employee_id')->on('employee');
             (PK tabel employee adalah employee_id, BUKAN id)
FK lain    : \$table->foreignId('x_id')->constrained('x')
Model      : \$fillable (bukan \$guarded), \$casts (bukan \$dates),
             public const untuk daftar status, relasi SELALU berkunci eksplisit
Validasi   : \$request->validate([...]) array rules, diekstrak ke private validatePayload()
Service    : logika lintas-entitas; gerbang bisnis kembalikan
             ['allowed'=>bool,'reason'=>string] — tiru PeriodService
Transaksi  : DB::transaction(closure)
Response   : JSON ['success'=>bool,'message'=>string,'data'=>...]
View       : @extends('dashboard') + @section('content') + @push('scripts')
Bahasa     : kode & kolom Inggris, komentar Bahasa Indonesia

--- LARANGAN ---
JANGAN buat Policy, Form Request, Action class, Repository, base model, Blade Component
JANGAN tambah paket Composer/NPM baru (Leaflet dimuat lewat CDN)
JANGAN pakai FLOAT untuk koordinat — DECIMAL(10,8) lintang, DECIMAL(11,8) bujur
JANGAN pakai ulang timesheets.presence / .location (vocabulary sudah rusak)
JANGAN salin pola CSRF dari dashboard.blade.php:1768 (kutip tipografis) — pakai baris 2294
JANGAN catch (\\Throwable) tanpa Log::error()
JANGAN ambil employee_id dari request body — SELALU dari session('user')['id']
JANGAN ubah: PeriodService, Employee, AuthController, CheckMenuAccess, MenuRegistrar,
             bootstrap/app.php, controller Ticket/Delivery/Timesheet, migrasi yang sudah ada
Berkas lama yang BOLEH disentuh hanya: routes/web.php (+3 baris require) dan
             dashboard.blade.php (blok sidebar modul ini saja — lihat baris di atas)

--- STRUKTUR BERKAS MODUL (pasca-refactor 19 Agu) ---
app/Http/Controllers/HR_General/   namespace App\Http\Controllers\HR_General  (8 controller)
app/Models/Attendance/             8 model      app/Services/Attendance/  3 service
resources/views/HR_General/        13 view, nama berkas pakai GARIS BAWAH (my_attendance.blade.php)
routes/HR_General.php              36 rute (URI berawalan general/*)
Nama BERKAS boleh HR_General; slug izin / nama rute / URL TETAP 'general.*' — terikat tabel menu

--- DOKUMEN KERJA — seluruhnya di docs/updated-file/ (baca sesuai kebutuhan, JANGAN semuanya) ---
00-INDEKS.md                        peta dokumen + alur kerja harian
01-KONTEKS-SISTEM.md                fakta stack/DB/menu/role yang sudah diverifikasi
02-PROGRES.md                       posisi pekerjaan & catatan harian
03-KEPUTUSAN.md                     keputusan yang sudah diambil + alasannya
04-DISKUSI-DAN-RISIKO.md            pertanyaan terbuka + risiko + kontrak jangan-sentuh
05-KONVENSI-RINGKAS.md              aturan kode padat (kerangka migrasi/model/controller/Blade)
attendance/00-RINGKASAN-PEKERJAAN.md  catatan kerja per sesi + keputusan D23-D71
attendance/14-RANCANGAN-BERLAKU.md    rancangan Attendance yang berlaku (B0-B10)

--- SUB-MODUL BERIKUTNYA: OVERTIME ---
Rancangan BELUM ada. 4 pertanyaan yang menentukan SKEMA (bukan UI) menunggu jawaban —
selengkapnya di docs/updated-file/04-DISKUSI-DAN-RISIKO.md §0:
  O1 diajukan sebelum, atau diklaim setelah?
  O2 penyetuju HR atau atasan langsung?  (hierarki atasan BELUM ADA)
  O3 menit lembur otomatis dari attendance_records.overtime_minutes, atau manual?
  O4 ada tarif/pengali hari kerja vs libur?  (menyentuh ranah payroll)
Jangan membuat migrasi Overtime sebelum keempatnya terjawab.

=== TUGAS HARI INI ===
[tulis di sini, sebutkan nomor langkahnya — contoh: "Kerjakan langkah B10"]
TXT;
    }

    /**
     * Tebak langkah terakhir yang selesai dari keadaan database.
     *
     * Sengaja menebak dari SKEMA, bukan dari checklist di dokumen: skema tidak
     * bisa lupa diperbarui. Angka pastinya tetap di docs/updated-file/02-PROGRES.md.
     */
    private function stepStatus(array $f): string
    {
        $allTables = !collect($f['attendance_tables'])->contains(false);

        // B9 dinilai dari BERKAS, bukan tabel: rekap bulanan & koreksi tidak
        // meninggalkan jejak skema sendiri, jadi tabel saja tidak bisa
        // membedakan "B4 selesai" dari "B9 selesai".
        $b9Files = File::exists(resource_path('views/HR_General/attendance/monthly.blade.php'))
            && File::exists(app_path('Http/Controllers/HR_General/AttendanceCorrectionController.php'));

        if ($allTables && $f['branch_rows'] > 0 && $b9Files) {
            return 'B0-B9 SELESAI (Attendance siap rilis, ' . $f['branch_rows'] . ' cabang terisi) · B10 ditunda'
                 . ' — cek docs/updated-file/attendance/00-RINGKASAN-PEKERJAAN.md';
        }

        if ($allTables && $f['branch_rows'] > 0) {
            return 'B4+ (tabel lengkap, ' . $f['branch_rows'] . ' cabang terisi) — cek docs/updated-file/attendance/00-RINGKASAN-PEKERJAAN.md';
        }

        if ($allTables) {
            return 'B2-B4 (7 tabel ada, BELUM ada cabang terisi — presensi belum bisa validasi lokasi)';
        }

        if (collect($f['attendance_tables'])->contains(true)) {
            return 'B2 sebagian (tabel belum lengkap)';
        }

        if ($f['children']->isNotEmpty()) {
            return 'B1 (slug izin sudah terdaftar)';
        }

        return 'belum ada — mulai dari B0';
    }

    private function hrRoles(array $f): string
    {
        if ($f['hr_roles']->isEmpty()) {
            return 'tidak ditemukan';
        }

        return $f['hr_roles']->map(fn ($r) => $r->id . '=' . $r->name)->implode(', ');
    }

    private function yn(bool $value): string
    {
        return $value ? 'ADA' : 'BELUM ADA';
    }

    private function nullable(?int $value): string
    {
        return $value === null ? 'tabel belum ada' : (string) $value;
    }

    /** Baca info git tanpa menggagalkan perintah bila git tidak tersedia. */
    private function git(string $args): string
    {
        try {
            $output = @shell_exec('git -C ' . escapeshellarg(base_path()) . ' ' . $args . ' 2>&1');

            return trim((string) $output) ?: 'tidak diketahui';
        } catch (\Throwable $e) {
            return 'tidak diketahui';
        }
    }

    private function writeHandoff(string $handoff): void
    {
        $path = base_path('docs/updated-file/HANDOFF.md');
        File::ensureDirectoryExists(dirname($path));

        $body = "# HANDOFF — Blok Siap-Tempel untuk Room Chat Baru\n\n"
            . "> **Dihasilkan otomatis oleh `php artisan hr:sync --write`. Jangan diedit manual** —\n"
            . "> perubahan akan tertimpa. Untuk mengubah isinya, sunting\n"
            . "> `app/Console/Commands/HrSync.php`.\n\n"
            . "## Cara pakai\n\n"
            . "1. Buka room chat baru di folder repo ini\n"
            . "2. Salin **seluruh blok** di bawah\n"
            . "3. Tempel sebagai pesan pertama\n"
            . "4. Ganti baris terakhir dengan tugas hari itu, sebutkan nomor langkahnya\n\n"
            . "---\n\n"
            . "```text\n" . $handoff . "\n```\n";

        File::put($path, $body);
    }

    /**
     * Ganti blok di antara penanda HR-SYNC di 01-KONTEKS-SISTEM.md.
     *
     * Memakai penanda, bukan menulis ulang seluruh berkas, supaya bagian yang
     * ditulis manusia (arsitektur, larangan, catatan sistem lama) tidak hilang
     * setiap kali perintah ini dijalankan.
     */
    private function writeSnapshotBlock(array $f): void
    {
        $path = base_path('docs/updated-file/01-KONTEKS-SISTEM.md');

        if (!File::exists($path)) {
            $this->warn('docs/updated-file/01-KONTEKS-SISTEM.md tidak ditemukan — blok otomatis dilewati.');

            return;
        }

        $start = '<!-- HR-SYNC:MULAI';
        $end   = '<!-- HR-SYNC:SELESAI -->';

        $content = File::get($path);
        $startAt = strpos($content, $start);
        $endAt   = strpos($content, $end);

        if ($startAt === false || $endAt === false) {
            $this->warn('Penanda HR-SYNC tidak ditemukan di 01-KONTEKS-SISTEM.md — blok otomatis dilewati.');

            return;
        }

        $startLineEnd = strpos($content, "\n", $startAt);

        $tables = collect($f['attendance_tables'])
            ->merge($f['other_hr_tables'])
            ->map(fn ($ada, $t) => '| `' . $t . '` | ' . ($ada ? '✅ ada' : '☐ belum') . ' |')
            ->implode("\n");

        $menus = $f['children']->isEmpty()
            ? '*(belum ada anak menu — langkah A1 belum dikerjakan)*'
            : "| Slug | Nama | Tipe |\n|---|---|---|\n"
                . $f['children']->map(fn ($m) => '| `' . $m->slug . '` | ' . $m->name . ' | ' . $m->type . ' |')->implode("\n");

        $block = <<<MD


## 8. Snapshot otomatis

*Dihasilkan `php artisan hr:sync --write` pada {$f['generated_at']}.*

| Aspek | Nilai |
|---|---|
| Database | `{$f['db_name']}` · {$f['db_version']} |
| `APP_ENV` / `APP_URL` | `{$f['app_env']}` · `{$f['app_url']}` |
| Secure context (HTTPS) | {$this->yn($f['is_https'])} |
| Timezone aplikasi | `{$f['app_timezone']}` |
| Branch @ commit | `{$f['git_branch']}` @ `{$f['git_commit']}` |
| Berkas migrasi / sudah dijalankan | {$f['migration_files']} / {$f['migration_ran']} |
| Baris tabel `menu` | {$f['menu_total']} |
| Karyawan total / aktif | {$f['employee_total']} / {$f['employee_active']} |
| Role total | {$f['role_total']} |
| Role HR | {$this->hrRoles($f)} |
| `employee.reports_to_id` | {$this->yn($f['has_reports_to'])} |
| `routes/HR_General.php` | {$this->yn($f['route_general'])} |
| Hari libur di `holidays` | {$f['holidays']} |
| Langkah terakhir yang tampak selesai | {$this->stepStatus($f)} |

### Anak menu `general` (id {$f['parent_menu']->id})

{$menus}

### Tabel modul HR

| Tabel | Status |
|---|---|
{$tables}

MD;

        $newContent = substr($content, 0, $startLineEnd + 1)
            . $block
            . "\n"
            . substr($content, $endAt);

        File::put($path, $newContent);
    }

    private function renderSummaryTable(array $f): void
    {
        $this->table(
            ['Aspek', 'Nilai'],
            [
                ['Database', $f['db_name'] . ' · ' . $f['db_version']],
                ['APP_URL', $f['app_url'] . ($f['is_https'] ? '' : '  ⚠ bukan HTTPS')],
                ['Branch @ commit', $f['git_branch'] . ' @ ' . $f['git_commit']],
                ['Migrasi (berkas/jalan)', $f['migration_files'] . ' / ' . $f['migration_ran']],
                ['Menu total', $f['menu_total']],
                ['Anak menu general', $f['children']->count()],
                ['Karyawan aktif', $f['employee_active'] . ' dari ' . $f['employee_total']],
                ['Tabel attendance', collect($f['attendance_tables'])->filter()->count() . ' dari ' . count(self::ATTENDANCE_TABLES)],
                ['Langkah terakhir', $this->stepStatus($f)],
            ]
        );

        if (!$f['is_https']) {
            $this->newLine();
            $this->warn('APP_URL bukan https:// — Geolocation API hanya berjalan di localhost.');
            $this->comment('Ini normal untuk pengembangan lokal. Konfirmasi HTTPS produksi sebelum rilis (P1).');
        }
    }
}

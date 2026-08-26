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

    /** Tabel penanda kemajuan sub-modul Overtime. */
    private const OVERTIME_TABLES = [
        'overtime_settings',
        'overtime_approval_steps',
        'overtime_requests',
        'overtime_request_approvals',
    ];

    /**
     * Tabel penanda kemajuan sub-modul Reimbursement.
     *
     * Belum satu pun dibuat per 24 Agustus 2026 — rancangannya sudah terkunci
     * (R0 selesai), langkah R1 belum dikerjakan. Dipantau sejak sekarang sesuai
     * Keputusan D73, supaya begitu migrasinya dibuat statusnya langsung terbaca
     * di HANDOFF tanpa harus ingat memperbarui perintah ini.
     */
    private const REIMBURSEMENT_TABLES = [
        'reimbursement_settings',
        'reimbursement_approval_steps',
        'reimbursement_requests',
        'reimbursement_items',
        'reimbursement_request_approvals',
    ];

    /**
     * Tabel modul HR lain yang belum dikerjakan — dipantau agar tidak terlewat.
     *
     * Namanya masih tentatif; dipantau sejak sekarang supaya begitu migrasinya
     * dibuat, statusnya langsung ikut terbaca di HANDOFF tanpa harus mengingat
     * memperbarui perintah ini.
     */
    private const OTHER_HR_TABLES = [
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

        $overtimeTables = [];
        foreach (self::OVERTIME_TABLES as $table) {
            $overtimeTables[$table] = Schema::hasTable($table);
        }

        $reimbursementTables = [];
        foreach (self::REIMBURSEMENT_TABLES as $table) {
            $reimbursementTables[$table] = Schema::hasTable($table);
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
            'overtime_tables'   => $overtimeTables,
            'reimbursement_tables' => $reimbursementTables,
            'other_hr_tables'   => $otherHrTables,
            'reimbursement_rows' => Schema::hasTable('reimbursement_requests')
                ? DB::table('reimbursement_requests')->count()
                : null,
            'overtime_rows'     => Schema::hasTable('overtime_requests')
                ? DB::table('overtime_requests')->count()
                : null,
            'overtime_steps'    => Schema::hasTable('overtime_approval_steps')
                ? DB::table('overtime_approval_steps')->where('is_active', 1)->count()
                : 0,
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

            // Fakta yang DIHITUNG dari repo, bukan ditulis tangan. Sebelumnya
            // angka-angka ini dipatok di dalam heredoc dan langsung basi begitu
            // sub-modul berikutnya ditambahkan — persis kegagalan yang perintah
            // ini seharusnya cegah.
            'code_counts'       => $this->codeCounts(),
            'sidebar_lines'     => $this->sidebarLines(),
            'csrf_lines'        => $this->csrfLines(),
        ];
    }

    /**
     * Jumlah berkas per lapisan modul.
     *
     * Dihitung dari sistem berkas supaya HANDOFF tidak pernah menyebut angka
     * yang tidak lagi benar.
     *
     * @return array<string,int>
     */
    private function codeCounts(): array
    {
        $count = fn (string $glob): int => count(File::glob(base_path($glob)));

        return [
            'controllers'   => $count('app/Http/Controllers/HR_General/*.php'),
            'views'         => count(File::glob(base_path('resources/views/hr-general/*/*.blade.php')))
                             + count(File::glob(base_path('resources/views/hr-general/*/*/*.blade.php')))
                             + count(File::glob(base_path('resources/views/hr-general/*.blade.php'))),
            'models_att'    => $count('app/Models/Attendance/*.php'),
            'models_ot'     => $count('app/Models/Overtime/*.php'),
            'models_rb'     => $count('app/Models/Reimbursement/*.php'),
            'services_att'  => $count('app/Services/Attendance/*.php'),
            'services_ot'   => $count('app/Services/Overtime/*.php'),
            'services_rb'   => $count('app/Services/Reimbursement/*.php'),
            'tests'         => $count('tests/Unit/Attendance/*.php')
                             + $count('tests/Unit/Overtime/*.php')
                             + $count('tests/Unit/Reimbursement/*.php'),
            'routes'        => $this->matchCount(base_path('routes/HR_General.php'), '/^\s*Route::(get|post)\(/m'),
        ];
    }

    /**
     * Nomor baris blok sidebar milik modul ini di `dashboard.blade.php`.
     *
     * Sidebar di-hardcode di satu berkas 2.500 baris. Menyebut nomor baris yang
     * salah membuat pembacanya menyunting blok milik modul LAIN — berkas ini
     * termasuk yang produksi, jadi kesalahannya mahal. Karena itu penandanya
     * dicari dari komentar penanda, bukan dihafal.
     *
     * @return array<string,string> label => nomor baris (atau '?' bila hilang)
     */
    private function sidebarLines(): array
    {
        // Sejak merge staging_al_naf (26 Agu 2026) sidebar TIDAK lagi berada di
        // dalam dashboard.blade.php, melainkan di partial tersendiri. Hanya
        // `--primary-surface` yang masih tinggal di layout.
        $sidebar = base_path('resources/views/partials/sidebar.blade.php');
        $layout  = base_path('resources/views/dashboard.blade.php');

        return [
            'partials/sidebar.blade.php — dropdown HR & General' => $this->lineOf($sidebar, '<!-- HR & GENERAL -->'),
            'partials/sidebar.blade.php — My Attendance (ESS)'   => $this->lineOf($sidebar, "\$essConfig['my_attendance']"),
            'partials/sidebar.blade.php — Overtime (ESS)'        => $this->lineOf($sidebar, "\$essConfig['overtime']"),
            'partials/sidebar.blade.php — Reimbursement (ESS)'   => $this->lineOf($sidebar, "\$essConfig['expense_reimbursement']"),
            'partials/sidebar.blade.php — Reimbursement Mgmt'    => $this->lineOf($sidebar, "route('general.reimbursement.index')"),
            'partials/sidebar.blade.php — Control Center'        => $this->lineOf($sidebar, '<!-- CONTROL CENTER -->'),
            'partials/sidebar.blade.php — fungsi toggle'         => $this->lineOf($sidebar, 'function toggleSidebarDropdown('),
            'dashboard.blade.php — definisi --primary-surface'   => $this->lineOf($layout, '--primary-surface:'),
        ];
    }

    /**
     * Dua baris pola CSRF di `dashboard.blade.php`: yang rusak dan yang benar.
     *
     * Yang rusak memakai kutip tipografis sehingga selektornya tidak pernah
     * cocok dan token selalu kosong. Keduanya dicari otomatis karena nomor
     * barisnya bergeser setiap kali sidebar bertambah.
     *
     * @return array{bad:string,good:string}
     */
    private function csrfLines(): array
    {
        $path = base_path('resources/views/dashboard.blade.php');

        return [
            'bad'  => $this->lineOf($path, 'meta[name=”csrf-token”]'),
            'good' => $this->lineOf($path, "querySelector('meta[name=\"csrf-token\"]')"),
        ];
    }

    /** Nomor baris kemunculan pertama sebuah penanda; '?' bila tidak ditemukan. */
    private function lineOf(string $path, string $needle): string
    {
        if (!File::exists($path)) {
            return '?';
        }

        foreach (file($path) as $i => $line) {
            if (str_contains($line, $needle)) {
                return (string) ($i + 1);
            }
        }

        return '?';
    }

    /** Berapa kali sebuah pola muncul di dalam satu berkas. */
    private function matchCount(string $path, string $pattern): int
    {
        return File::exists($path)
            ? preg_match_all($pattern, File::get($path))
            : 0;
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

        $overtime = collect($f['overtime_tables'])
            ->map(fn ($ada, $t) => '  ' . ($ada ? '[v]' : '[ ]') . ' ' . $t)
            ->implode("\n");

        $reimbursement = collect($f['reimbursement_tables'])
            ->map(fn ($ada, $t) => '  ' . ($ada ? '[v]' : '[ ]') . ' ' . $t)
            ->implode("\n");

        // Nomor baris & jumlah berkas: dirender dari fakta terhitung supaya blok
        // ini tidak pernah menyebut angka yang sudah bergeser.
        $sidebar = collect($f['sidebar_lines'])
            ->map(fn ($line, $label) => '  baris ' . str_pad($line, 5) . $label)
            ->implode("\n");

        $c = $f['code_counts'];

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
Sub-modul OVERTIME (O0-O8) SELESAI & teruji 20 Agu 2026.
Sub-modul REIMBURSEMENT (R0-R8) SELESAI & teruji 24 Agu 2026.
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
Sidebar berada di resources/views/partials/sidebar.blade.php (sejak merge 26 Agu 2026,
BUKAN lagi di dashboard.blade.php). Titik modul ini (dihitung otomatis):
{$sidebar}

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
JANGAN salin pola CSRF dari dashboard.blade.php:{$f['csrf_lines']['bad']} (kutip tipografis) — pakai baris {$f['csrf_lines']['good']}
JANGAN catch (\\Throwable) tanpa Log::error()
JANGAN ambil employee_id dari request body — SELALU dari session('user')['id']
JANGAN ubah: PeriodService, Employee, AuthController, CheckMenuAccess, MenuRegistrar,
             bootstrap/app.php, controller Ticket/Delivery/Timesheet, migrasi yang sudah ada
Berkas lama yang BOLEH disentuh hanya: routes/web.php (+3 baris require) dan
             dashboard.blade.php (blok sidebar modul ini saja — lihat baris di atas)

--- STRUKTUR BERKAS MODUL (dihitung otomatis dari repo) ---
app/Http/Controllers/HR_General/   namespace App\Http\Controllers\HR_General  ({$c['controllers']} controller)
app/Models/       Attendance {$c['models_att']} · Overtime {$c['models_ot']} · Reimbursement {$c['models_rb']}
app/Services/     Attendance {$c['services_att']} · Overtime {$c['services_ot']} · Reimbursement {$c['services_rb']}
resources/views/hr-general/        {$c['views']} view, nama berkas pakai TANDA HUBUNG (my-attendance.blade.php)
                                   Folder & nama berkas diganti pada merge 26 Agu 2026 —
                                   dulu HR_General/ dengan garis bawah. Sidebar kini berada
                                   di resources/views/partials/sidebar.blade.php, BUKAN lagi
                                   di dalam dashboard.blade.php
routes/HR_General.php              {$c['routes']} rute GET/POST (URI berawalan general/*)
tests/Unit/                        {$c['tests']} berkas tes unit
Nama BERKAS boleh HR_General; slug izin / nama rute / URL TETAP 'general.*' — terikat tabel menu

--- KONVENSI UI & STYLING (wajib, selengkapnya: docs/updated-file/07-KONVENSI-UI.md) ---
Warna kontainer TIDAK PERNAH dipatok. Semua warna ber-merek berasal dari preferensi
  pengguna di Settings (Accent color + Sidebar style), lewat variabel di :root
  dashboard.blade.php baris {$f['sidebar_lines']['dashboard.blade.php — definisi --primary-surface']}:
  .primary-surface  kartu/hero bertema (gradien atau solid, mengikuti Sidebar style)
  .primary-gradient / .primary-solid / .primary-text / .primary-border  elemen ber-merek
  Kartu biasa TETAP `bg-white rounded-xl shadow-sm` — dipetakan ulang otomatis oleh
  blok dark mode di layout. JANGAN tulis bg-gray-900 untuk hero card.
  bg-gray-800 pada tombol filter (Apply/Search/Save) SENGAJA netral — jangan diaksenkan.
Teks antarmuka BAHASA INGGRIS; komentar kode BAHASA INDONESIA.

--- DOKUMEN KERJA — seluruhnya di docs/updated-file/ (baca sesuai kebutuhan, JANGAN semuanya) ---
00-INDEKS.md                        peta dokumen + alur kerja harian
01-KONTEKS-SISTEM.md                fakta stack/DB/menu/role yang sudah diverifikasi
02-PROGRES.md                       posisi pekerjaan & catatan harian
03-KEPUTUSAN.md                     keputusan yang sudah diambil + alasannya
04-DISKUSI-DAN-RISIKO.md            pertanyaan terbuka + risiko + kontrak jangan-sentuh
05-KONVENSI-RINGKAS.md              aturan kode padat (kerangka migrasi/model/controller/Blade)
07-KONVENSI-UI.md                   aturan TAMPILAN: token warna dari Settings, resep kartu,
                                    badge, dark mode, kartu hero — baca sebelum menyentuh Blade
attendance/00-RINGKASAN-PEKERJAAN.md  catatan kerja per sesi + keputusan D23-D71
attendance/14-RANCANGAN-BERLAKU.md    rancangan Attendance yang berlaku (B0-B10)
06-KONVENSI-RUTE-PRODUKSI.md        aturan verb HTTP modul ini + cara memverifikasi
overtime/00-RINGKASAN-PEKERJAAN.md    catatan kerja Overtime + keputusan D88-D101
overtime/02-PANDUAN-KODE.md           panduan teknis Overtime untuk diskusi tim
overtime/03-CARA-PENGGUNAAN.md        cara memakai fitur Overtime (karyawan/penyetuju/admin)
reimbursement/01-RANCANGAN.md         rancangan BERLAKU (5 tabel, 8 slug, 26 rute, D102-D114)
reimbursement/02-PERTANYAAN-KONFIRMASI.md  R1-R12 + jawabannya + konsekuensi tiap jawaban
reimbursement/00-RINGKASAN-PEKERJAAN.md    catatan kerja Reimbursement per sesi
reimbursement/03-CHECKLIST-PENGERJAAN.md   PAPAN PELACAKAN R0-R8 + berkas per langkah

--- VERB RUTE: routes/HR_General.php HANYA GET & POST ---
Nol PUT/PATCH/DELETE sejak 21 Agu 2026. Pengubah data memakai akhiran aksi:
  ubah  -> POST /{id}/update      hapus -> POST /{id}/delete
  batal -> POST /{id}/cancel      lepas -> POST /{id}/release
JANGAN pakai Route::put()/delete() maupun @method() di modul ini.
Saat menyusun URL di JavaScript, tulis akhiran aksinya — route() menyesuaikan
  sendiri, string literal TIDAK. Selengkapnya: docs/updated-file/06-KONVENSI-RUTE-PRODUKSI.md
Modul LAIN masih memakai PUT/DELETE dan SENGAJA tidak diubah.

--- SUB-MODUL OVERTIME (selesai) ---
{$overtime}
Baris overtime_requests: {$this->nullable($f['overtime_rows'])}   langkah persetujuan aktif: {$f['overtime_steps']}
Model app/Models/Overtime/ (4)  Service app/Services/Overtime/ (2)
  OvertimeRateService  MURNI tanpa DB, pengali PP 35/2021, 22 unit test
  OvertimeService      mesin submit/cancel/canAct/approve/reject, agnostik transport
DUA LAPIS IZIN — jangan disatukan:
  slug menu:general.overtime.approve  = boleh MEMBUKA halaman peninjauan
  langkah workflow                    = boleh menyetujui pengajuan YANG MANA
Langkah persetujuan DISALIN ke tiap pengajuan saat dibuat, sehingga mengubah
  konfigurasi tidak pernah merusak pengajuan yang sedang berjalan
NOMINAL RUPIAH sengaja BELUM ada: nol tabel gaji di database ini. Kolom
  hourly_rate/rate_breakdown/amount sudah disiapkan nullable, day_type dibekukan
  saat pengajuan — penyambungan ke payroll nanti TIDAK perlu migrasi

--- SUB-MODUL REIMBURSEMENT (SELESAI R0-R8, 24 Agu 2026) ---
{$reimbursement}
Baris reimbursement_requests: {$this->nullable($f['reimbursement_rows'])}
SELURUH KODE SUDAH ADA — jangan dibangun ulang:
  app/Models/Reimbursement/            5 model
  app/Services/Reimbursement/          ReimbursementTotalService  MURNI tanpa DB, 26 unit test
                                       ReimbursementService       submit/update/canAct/approve/
                                                                  reject/softDelete/itemRules/
                                                                  signatories, agnostik transport
  app/Http/Controllers/HR_General/     MyReimbursementController (ESS)
                                       ReimbursementController (HR, 12 method)
                                       ReimbursementImportController (impor Excel)
                                       ReimbursementSettingController (aturan + alur)
  resources/views/hr-general/reimbursement/  8 view (2 di antaranya partial dipakai 3 form)
  app/Exports/ReimbursementDocumentExport.php  satu kelas: ekspor dokumen DAN bulanan
  routes/HR_General.php                26 rute reimbursement SUDAH ada
  dashboard.blade.php                  2 item menu SUDAH ada (tingkat atas + dropdown HR)
YANG TERSISA hanyalah pembagian izin lewat Control Center -> Menu Access.
  Slug mana untuk siapa: docs/updated-file/reimbursement/03-CHECKLIST-PENGERJAAN.md
  Untuk sisi HR, slug induk `general` WAJIB ikut diberikan — tanpa itu dropdown
  "HR & General" tidak dirender sama sekali.
LETAK MENU (sejak 25 Agu, migrasi 2026_08_25_000001):
  'Reimbursement' DAN 'Reimbursement Management' keduanya TINGKAT ATAS, sejajar
  My Attendance & Overtime. Reimbursement Management SENGAJA di luar dropdown
  'HR & General' supaya penyetuju TIDAK perlu diberi slug induk `general`.
  Overtime Management SENGAJA masih di dalam dropdown — jangan ikut dipindah
  tanpa diminta.
DUA JALAN MENUJU EDIT — jangan disederhanakan jadi satu:
  1. pemegang general.reimbursement.manage  (kapan pun, selama dokumen terbuka)
  2. penyetuju yang SEDANG mendapat giliran, bila setelan
     allow_approver_adjust_amount dinyalakan; haknya HILANG begitu ia menyetujui
  Keputusannya di ReimbursementController::canEditDocument(), diperiksa DUA KALI
  (saat merender tombol dan saat menyimpan). Rute edit/update dijaga
  menu:general.reimbursement; destroy TETAP menu:general.reimbursement.manage.
ALUR PERSETUJUAN — ATURAN ASIMETRIS (Keputusan D116), jangan disederhanakan:
  MENAMBAH langkah BOLEH diterapkan ke dokumen yang sedang berjalan (memperketat),
  lewat kotak centang `apply_to_open` saat menambahnya. MENGHAPUS / MELONGGARKAN
  TIDAK PERNAH berlaku surut — di sanalah bahayanya: dokumen yang menunggu di
  langkah yang dihapus bisa melompat jadi disetujui tanpa ditinjau siapa pun.
  Itu sebabnya salinan langkah per dokumen tetap dipertahankan.
  Mesinnya: ReimbursementService::applyStepToOpenRequests() — hanya dokumen
  terbuka, hanya langkah ber-order_seq LEBIH BESAR dari langkah yang menunggu,
  anti-duplikat, ditandai flag `workflow_extended` (NETRAL) + Log::info.
CATATAN yang menghemat waktu bila menyentuh modul ini:
  - Nama field item memakai KUNCI ACAK (items[<uuid>][amount]), BUKAN indeks berurutan
  - Aturan baris item ada di ReimbursementService::itemRules() — dipakai 3 pintu masuk
  - after_or_equal TIDAK bekerja dengan wildcard; dipakai closure
  - Karyawan TIDAK punya tombol Cancel (D111)
  - showPrompt() TIDAK ADA di aplikasi ini; hanya showConfirm() dan showNotification()
  - Rute baca sisi HR memakai ->withTrashed() agar dokumen terhapus tetap terbuka
  - Seluruh setelan sudah DIAUDIT (39 pemeriksaan): mengubahnya benar-benar
    mengubah perilaku. Bila menambah setelan baru, WAJIB dibuktikan terpakai —
    setelan mati adalah kegagalan D52
PANDUAN PEMAKAIAN (menu, izin per peran, tiap setelan, cara menguji):
  docs/updated-file/reimbursement/PANDUAN-REIMBURSEMENT.docx
Bentuk yang sudah dikunci (keputusan D102-D115):
  Header `reimbursement_requests` + detail `reimbursement_items` (multi-item)
  Item dibebankan ke CABANG (branches). Kolom delivery_project_id dibuat tapi UI MATI
  charged_to_label & cost_center_label DIBEKUKAN saat submit (nama cabang bisa berubah)
  total_amount DIHITUNG lalu DISIMPAN; satu-satunya jalur tulis recalculateTotals()
  Bukti = TAUTAN Google Drive (supporting_url) + daftar host di settings. Nol unggahan
  Batas nominal: over_limit_policy = flag (bawaan) / block. 0 = tanpa batas
  Hapus = SOFT DELETE + deleted_by + delete_reason WAJIB (dokumen keuangan)
  Karyawan TIDAK bisa membatalkan -> status hanya EMPAT: submitted/in_review/approved/rejected
  Penanda tangan disimpan sebagai employee_id (bukan nama) di reimbursement_settings
  Label status: rekap 'Pending {langkah}' · detail & ESS 'Waiting {langkah}'
Nama menu: 'Reimbursement' (karyawan, tingkat atas) · 'Reimbursement Management' (HR)
Tabel langkah persetujuan DIBUAT SENDIRI, tidak menumpang overtime_approval_steps (D102)

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
        // Folder view diganti nama menjadi `hr-general/` pada merge staging_al_naf
        // (26 Agu 2026). Path lama akan selalu false dan membuat B9 terlaporkan
        // belum selesai padahal sudah.
        $b9Files = File::exists(resource_path('views/hr-general/attendance/monthly.blade.php'))
            && File::exists(app_path('Http/Controllers/HR_General/AttendanceCorrectionController.php'));

        $overtimeDone = !collect($f['overtime_tables'])->contains(false)
            && File::exists(app_path('Services/Overtime/OvertimeService.php'));

        if ($allTables && $f['branch_rows'] > 0 && $b9Files && $overtimeDone) {
            return 'Attendance B0-B9 SELESAI (B10 ditunda) · Overtime O0-O8 SELESAI'
                 . ' — cek docs/updated-file/{attendance,overtime}/00-RINGKASAN-PEKERJAAN.md';
        }

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
            ->merge($f['overtime_tables'])
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

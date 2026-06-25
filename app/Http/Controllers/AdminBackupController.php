<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminBackupController extends Controller
{
    /**
     * Password default untuk akun yang dibuat lewat import employee.
     * Employee login dengan ECI/email + password ini, lalu (karena
     * is_already_cp = false) sistem memaksa set-password via link email.
     */
    private const DEFAULT_IMPORT_PASSWORD = 'initial';

    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
    }

    private function assertHeadOrAdmin(): bool
    {
        $roleId = (int) session('user.role.id');
        return $roleId === RoleId::EC_ADMINISTRATOR->value
            || in_array($roleId, RoleId::HEAD_GROUP, true)
            || $roleId === RoleId::DELIVERY_RPMO_HEAD->value;
    }

    private function backupDisk()
    {
        return Storage::disk('local');
    }

    private function backupDir(): string
    {
        return 'backups';
    }

    // ── Page ─────────────────────────────────────────────────────────────────

    public function page()
    {
        if (!$this->assertAdmin()) abort(403);
        return view('admin.backup');
    }

    // ── DB Backup ─────────────────────────────────────────────────────────────

    public function listBackups()
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        try {
            $disk  = $this->backupDisk();
            $dir   = $this->backupDir();
            $files = $disk->exists($dir) ? $disk->files($dir) : [];

            $backups = collect($files)
                ->filter(fn($f) => str_ends_with($f, '.sql') || str_ends_with($f, '.sql.gz'))
                ->map(function ($f) use ($disk) {
                    $name = basename($f);
                    return [
                        'filename'   => $name,
                        'size'       => $this->formatBytes($disk->size($f)),
                        'size_bytes' => $disk->size($f),
                        'created_at' => date('Y-m-d H:i:s', $disk->lastModified($f)),
                    ];
                })
                ->sortByDesc('created_at')
                ->values();

            return response()->json(['success' => true, 'data' => $backups]);
        } catch (\Exception $e) {
            Log::error('AdminBackupController@listBackups', ['error' => $e->getMessage(), 'error_at' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'Failed to list backups'], 500);
        }
    }

    public function createBackup(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        try {
            $db       = config('database.connections.mysql');
            $host     = $db['host'];
            $port     = $db['port'] ?? 3306;
            $dbName   = $db['database'];
            $user     = $db['username'];
            $pass     = $db['password'];

            $mysqldump = $this->findMysqldump();
            if (!$mysqldump) {
                return response()->json(['success' => false, 'message' => 'mysqldump not found on this server'], 500);
            }

            $filename  = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $dir       = storage_path('app/' . $this->backupDir());
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filepath  = $dir . DIRECTORY_SEPARATOR . $filename;

            // Build command — redirect stderr to a temp file to capture errors.
            // escapeshellarg() is used on all platforms; on Windows it wraps with
            // double-quotes which mysqldump accepts for the -p argument.
            $passArg = $pass !== '' ? '-p' . escapeshellarg($pass) : '';
            $errFile = $filepath . '.err';
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf(
                    '"%s" -h %s -P %s -u %s %s %s > "%s" 2>"%s"',
                    $mysqldump,
                    escapeshellarg($host),
                    $port,
                    escapeshellarg($user),
                    $passArg,
                    escapeshellarg($dbName),
                    $filepath,
                    $errFile
                );
            } else {
                $cmd = sprintf(
                    '%s -h %s -P %s -u %s %s %s > %s 2>%s',
                    escapeshellarg($mysqldump),
                    escapeshellarg($host),
                    $port,
                    escapeshellarg($user),
                    $passArg,
                    escapeshellarg($dbName),
                    escapeshellarg($filepath),
                    escapeshellarg($errFile)
                );
            }

            exec($cmd, $output, $exitCode);

            // Clean up error file
            if (file_exists($errFile)) @unlink($errFile);

            if ($exitCode !== 0 || !file_exists($filepath) || filesize($filepath) === 0) {
                if (file_exists($filepath)) @unlink($filepath);
                return response()->json(['success' => false, 'message' => 'Backup failed (exit code ' . $exitCode . ')'], 500);
            }

            $size = $this->formatBytes(filesize($filepath));

            Log::info('AdminBackupController: backup created', [
                'filename' => $filename,
                'size'     => $size,
                'by'       => session('user.eci') ?? session('user.name') ?? 'admin',
            ]);

            return response()->json([
                'success'  => true,
                'message'  => "Backup created: {$filename} ({$size})",
                'filename' => $filename,
                'size'     => $size,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminBackupController@createBackup', ['error' => $e->getMessage(), 'error_at' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    public function downloadBackup(string $filename)
    {
        if (!$this->assertAdmin()) abort(403);

        // Guard: only allow safe filenames
        if (!preg_match('/^backup_[\d_\-]+\.sql(\.gz)?$/', $filename)) {
            abort(404);
        }

        $path = storage_path('app/' . $this->backupDir() . '/' . $filename);
        abort_if(!file_exists($path), 404);

        Log::info('AdminBackupController: backup downloaded', [
            'filename' => $filename,
            'by'       => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->download($path, $filename);
    }

    public function deleteBackup(string $filename)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        if (!preg_match('/^backup_[\d_\-]+\.sql(\.gz)?$/', $filename)) {
            return response()->json(['success' => false, 'message' => 'Invalid filename'], 422);
        }

        $path = storage_path('app/' . $this->backupDir() . '/' . $filename);
        if (!file_exists($path)) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        unlink($path);
        Log::info('AdminBackupController: backup deleted', ['filename' => $filename]);
        return response()->json(['success' => true, 'message' => 'Backup deleted']);
    }

    // ── Export Employee ───────────────────────────────────────────────────────

    public function exportEmployees()
    {
        if (!$this->assertAdmin()) abort(403);

        $rows = DB::table('employee as e')
            ->leftJoin('employee_basic_data as b', 'e.employee_id', '=', 'b.employee_id')
            ->leftJoin('employee_role_assignment as era', 'e.employee_id', '=', 'era.employee_id')
            ->leftJoin('employee_role as r', 'era.role_id', '=', 'r.id')
            ->leftJoin('employee_identification as ei', function ($join) {
                $join->on('ei.employee_id', '=', 'e.employee_id')
                     ->where('ei.identification_type', 'KTP');
            })
            ->select(
                'e.eci', DB::raw("GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') as role_name"), 'e.is_active',
                'b.title', 'b.nick_name', 'b.gender', 'b.religion',
                'b.first_name', 'b.last_name',
                'b.marital_status', 'b.birth_date', 'b.birth_place',
                'b.personnel_area', 'b.personnel_subarea',
                'b.employee_group', 'b.employee_subgroup',
                'b.position', 'b.division', 'b.department',
                'b.home_base', 'b.grade',
                'b.since_date',
                'ei.identification_number as nik'
            )
            ->orderBy('e.employee_id')
            ->get();

        $filename = 'employees_' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'ECI', 'role', 'status',
                'title', 'nick_name', 'gender', 'religion',
                'first_name', 'last_name',
                'marital_status', 'birth_date', 'birth_place',
                'personnel_area', 'personnel_subarea',
                'employee_group', 'employee_subgroup',
                'position', 'division', 'department',
                'home_base', 'grade',
                'since_date', 'nik',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->eci, $r->role_name, $r->is_active ? 'Active' : 'Inactive',
                    $r->title, $r->nick_name, $r->gender, $r->religion,
                    $r->first_name, $r->last_name,
                    $r->marital_status, $r->birth_date, $r->birth_place,
                    $r->personnel_area, $r->personnel_subarea,
                    $r->employee_group, $r->employee_subgroup,
                    $r->position, $r->division, $r->department,
                    $r->home_base, $r->grade,
                    $r->since_date, $r->nik,
                ]);
            }
            fclose($handle);
        };

        Log::info('AdminBackupController: employee export', [
            'count' => $rows->count(),
            'by'    => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->stream($callback, 200, $headers);
    }

    // ── Export Ticket ─────────────────────────────────────────────────────────

    public function exportTickets(Request $request)
    {
        if (!$this->assertAdmin()) abort(403);

        $year  = (int) $request->get('year',  date('Y'));
        $month = (int) $request->get('month', 0); // 0 = semua bulan dalam tahun tsb

        $query = DB::table('ticket as t')
            ->leftJoin('customer as c',              't.customer_id',   '=', 'c.customer_id')
            ->leftJoin('customer_basic_data as cbd', 'c.customer_id',   '=', 'cbd.customer_id')
            ->leftJoin('employee as e',              't.ticket_lead_id',   '=', 'e.employee_id')
            ->leftJoin('employee_basic_data as b',   'e.employee_id',   '=', 'b.employee_id')
            ->whereNull('t.deleted_at')
            ->whereYear('t.created_at', $year);

        if ($month > 0) {
            $query->whereMonth('t.created_at', $month);
        }

        $rows = $query->select(
                't.ticket_id', 't.ticket_number', 't.subject', 't.status',
                't.ticket_priority as priority', 't.type', 't.category',
                'c.customer_code', 'cbd.name_1 as company_name',
                'e.eci as pic_eci',
                DB::raw("CONCAT(COALESCE(b.first_name,''), ' ', COALESCE(b.last_name,'')) as pic_name"),
                't.created_at', 't.updated_at'
            )
            ->orderBy('t.created_at')
            ->get();

        $periodLabel = $month > 0
            ? date('F', mktime(0, 0, 0, $month, 1)) . '_' . $year
            : 'fullYear_' . $year;

        $filename = 'tickets_' . $periodLabel . '_' . date('Ymd') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Ticket ID', 'Ticket Number', 'Subject', 'Status', 'Priority', 'Type', 'Category',
                'Customer Code', 'Company Name',
                'PIC ECI', 'PIC Name',
                'Created At', 'Updated At',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->ticket_id, $r->ticket_number, $r->subject, $r->status,
                    $r->priority, $r->type, $r->category ?? '',
                    $r->customer_code ?? '', $r->company_name ?? '',
                    $r->pic_eci ?? '', trim($r->pic_name ?? ''),
                    $r->created_at, $r->updated_at,
                ]);
            }
            fclose($handle);
        };

        Log::info('AdminBackupController: ticket export', [
            'year'  => $year,
            'month' => $month ?: 'all',
            'count' => $rows->count(),
            'by'    => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->stream($callback, 200, $headers);
    }

    // ── Export Customer ───────────────────────────────────────────────────────

    public function exportCustomers()
    {
        if (!$this->assertAdmin()) abort(403);

        // Alamat utama (baris pertama / address_id terkecil) per customer, agar
        // tidak menggandakan baris customer saat ada >1 alamat.
        $firstAddr = DB::table('customer_address')
            ->selectRaw('MIN(address_id) as address_id, customer_id')
            ->groupBy('customer_id');

        $rows = DB::table('customer as c')
            ->leftJoin('customer_basic_data as b', 'c.customer_id', '=', 'b.customer_id')
            ->leftJoin('customer as p', 'c.parent_customer_id', '=', 'p.customer_id')
            ->leftJoin('customer_groups as cg', 'c.customer_group_id', '=', 'cg.id')
            ->leftJoinSub($firstAddr, 'fa', 'c.customer_id', '=', 'fa.customer_id')
            ->leftJoin('customer_address as a', 'fa.address_id', '=', 'a.address_id')
            ->select(
                'c.customer_id', 'c.customer_code', 'c.email', 'c.is_active',
                'b.title', 'b.name_1', 'b.name_2',
                // Customer Group struktural — utamakan nama dari relasi customer_groups,
                // fallback ke kolom teks lama bila FK belum terisi.
                DB::raw('COALESCE(cg.name, b.customer_group) as customer_group'),
                'b.customer_category', 'b.industry_sector',
                'b.ec_account_executive', 'b.sap_account_executive',
                'p.customer_code as parent_customer_code',
                'a.telephone', 'a.fax', 'a.full_address', 'a.building_name',
                'a.street', 'a.postal_code', 'a.country', 'a.region',
                'a.city', 'a.district', 'a.rural_urban_village',
                'c.created_at'
            )
            ->orderBy('c.customer_id')
            ->get();

        $filename = 'customers_' . date('Y-m-d') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Customer ID', 'Customer Code', 'Email', 'Status',
                'Title', 'Company Name', 'Name 2',
                'Customer Group', 'Customer Category', 'Industry Sector',
                'EC Account Executive', 'SAP Account Executive',
                'Parent Customer Code',
                'Phone', 'Fax',
                'Alamat Lengkap', 'Nama Gedung/Tempat', 'Street',
                'Postal Code', 'Country', 'Region/Province',
                'City', 'District', 'Urban Villages',
                'Created At',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->customer_id, $r->customer_code ?? '', $r->email ?? '',
                    $r->is_active ? 'Active' : 'Inactive',
                    $r->title ?? '', $r->name_1 ?? '', $r->name_2 ?? '',
                    $r->customer_group ?? '', $r->customer_category ?? '', $r->industry_sector ?? '',
                    $r->ec_account_executive ?? '', $r->sap_account_executive ?? '',
                    $r->parent_customer_code ?? '',
                    $r->telephone ?? '', $r->fax ?? '',
                    $r->full_address ?? '', $r->building_name ?? '', $r->street ?? '',
                    $r->postal_code ?? '', $r->country ?? '', $r->region ?? '',
                    $r->city ?? '', $r->district ?? '', $r->rural_urban_village ?? '',
                    $r->created_at,
                ]);
            }
            fclose($handle);
        };

        Log::info('AdminBackupController: customer export', [
            'count' => $rows->count(),
            'by'    => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->stream($callback, 200, $headers);
    }

    // ── Template Downloads ────────────────────────────────────────────────────

    public function templateEmployees()
    {
        if (!$this->assertAdmin()) abort(403);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            // Header columns — snake_case, compatible with both full names and Excel-truncated variants
            [
                'ECI', 'role', 'status', 'email', 'phone',
                'title', 'nick_name', 'gender', 'religion',
                'first_name', 'last_name',
                'marital_status', 'birth_date', 'birth_place',
                'personnel_area', 'personnel_subarea',
                'employee_group', 'employee_subgroup',
                'position', 'division', 'department',
                'home_base', 'grade',
                'since_date', 'nik',
            ],
            // Example row
            [
                'ECI001', 'Delivery Support User', 'Active', 'john.doe@example.com', '081234567890',
                'Mr.', 'John', 'Male', 'Islam',
                'John', 'Doe',
                'Single', '1990-01-15', 'Jakarta',
                'Area A', 'Sub Area A',
                'Group 1', 'Subgroup 1',
                'Consultant', 'IT', 'Support',
                'Jakarta', 'Junior Consultant',
                '2023-01-01', '3201010101900001',
            ],
        ]);

        $writer = new Xlsx($spreadsheet);
        return response()->stream(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="employees_import_template.xlsx"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    public function templateCustomers()
    {
        if (!$this->assertAdmin()) abort(403);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'Customer Code', 'Email', 'Status',
                'Title', 'Company Name', 'Name 2',
                'Customer Group', 'Customer Category', 'Industry Sector',
                'EC Account Executive', 'SAP Account Executive',
                'Parent Customer Code',
                // ── Komunikasi & Alamat (1 alamat utama per baris) ──
                'Phone', 'Fax',
                'Alamat Lengkap', 'Nama Gedung/Tempat', 'Street',
                'Postal Code', 'Country', 'Region/Province',
                'City', 'District', 'Urban Villages',
            ],
            [
                'MANTAP', 'company@example.com', 'Active',
                'PT', 'Bank Mandiri Taspen', '',
                'BUMN', '', 'Technology',
                'K25019', '',
                '',
                '+62 21 2123 1772', '+62 21 2123 1984',
                'Graha Mantap, Jl. Proklamasi No. 31, Menteng, Jakarta Pusat 10320',
                'Graha Mantap', 'Jl. Proklamasi No. 31',
                '10320', 'Indonesia', 'DKI Jakarta',
                'Jakarta Pusat', 'Menteng', '',
            ],
        ]);

        $writer = new Xlsx($spreadsheet);
        return response()->stream(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="customers_import_template.xlsx"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    // ── Import Employee ───────────────────────────────────────────────────────

    public function importEmployees(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        set_time_limit(300);

        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:20480']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        // Auto-detect delimiter: Excel Indonesia sering export dengan ";" bukan ","
        $firstLine = fgets($handle);
        rewind($handle);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        else fseek($handle, 3);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'File CSV kosong atau tidak valid'], 422);
        }

        // Alias map: canonical field => accepted header variants (handles full names,
        // snake_case, Excel-truncated variants, and old friendly-name format)
        $aliasMap = [
            'eci'               => ['eci', 'ECI'],
            'role'              => ['role', 'Role'],
            'status'            => ['status', 'Status'],
            'title'             => ['tit', 'title', 'Title'],
            'nick_name'         => ['nick_nam', 'nick_name', 'Nick Name'],
            'gender'            => ['gender', 'Gender'],
            'religion'          => ['religio', 'religion', 'Religion'],
            'first_name'        => ['first_name', 'First Name'],
            'last_name'         => ['last_name', 'Last Name'],
            'marital_status'    => ['marital_statu', 'marital_status', 'Marital Status'],
            'birth_date'        => ['birth_da', 'birth_date', 'Birth Date'],
            'birth_place'       => ['birth_pla', 'birth_place', 'Birth Place'],
            'personnel_area'    => ['personnel_are', 'personnel_area', 'Personnel Area'],
            'personnel_subarea' => ['personnel_subare', 'personnel_subarea', 'Personnel Subarea', 'Personnel Sub Area'],
            'employee_group'    => ['employee_grou', 'employee_group', 'Employee Group'],
            'employee_subgroup' => ['employee_subgrou', 'employee_subgroup', 'Employee Subgroup'],
            'position'          => ['position', 'Position'],
            'division'          => ['division', 'Division'],
            'department'        => ['department', 'Department'],
            'home_base'         => ['home_base', 'Home Base', 'homebase', 'based', 'Based'],
            'grade'             => ['grade', 'Grade', 'grade / level', 'Grade / Level', 'grade/level', 'Grade/Level'],
            'since_date'        => ['since_date', 'Since Date'],
            'nik'               => ['nik', 'NIK', 'nik (identification_type)', 'NIK (identification_type)'],
            'email'             => ['email', 'Email', 'email_work', 'Email Work'],
            // Cell phone → disimpan ke employee_address.cell_phone (alamat primary),
            // bukan auth_users.phone (login identifier).
            'cell_phone'        => ['phone', 'Phone', 'cell_phone', 'Cell Phone', 'cellphone', 'no_hp', 'No HP'],
        ];

        // Build colIndex: canonical_field => column index in CSV
        $buildColIndex = function (array $headerRow) use ($aliasMap): array {
            $idx = [];
            foreach ($aliasMap as $field => $aliases) {
                foreach ($headerRow as $i => $header) {
                    $h = strtolower(trim($header));
                    foreach ($aliases as $alias) {
                        if (strtolower(trim($alias)) === $h) {
                            $idx[$field] = $i;
                            break 2;
                        }
                    }
                }
            }
            return $idx;
        };

        // Cari baris header asli. File dari Excel kadang punya baris judul
        // ("Employee") sebelum baris header sebenarnya — lewati sampai ketemu
        // baris yang memuat kolom "ECI" (maks. 5 baris pertama).
        $colIndex = $buildColIndex($rawHeaders);
        $headerAttempts = 0;
        while (!isset($colIndex['eci']) && $headerAttempts < 5) {
            $next = fgetcsv($handle, 0, $delimiter);
            if ($next === false) break;
            $headerAttempts++;
            $colIndex = $buildColIndex($next);
        }

        if (!isset($colIndex['eci'])) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Kolom "ECI" wajib ada di file'], 422);
        }

        $row = []; // referenced in closure
        $get = function (string $field) use ($colIndex, &$row): ?string {
            if (!isset($colIndex[$field])) return null;
            $val = trim($row[$colIndex[$field]] ?? '');
            return $val !== '' ? $val : null;
        };

        $roles    = DB::table('employee_role')->pluck('id', 'name');
        // Lookup role case-insensitive (key: lowercase+trim nama → id). Kolom CSV
        // "role" bisa berisi BANYAK role dipisah koma; tiap nama dicocokkan ke sini.
        $rolesCI = collect($roles)->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id])->all();

        // Alias role: nama jabatan di CSV yang tak persis sama dgn nama role di DB.
        // Key: lowercase+trim nama CSV → nama role kanonik di DB. Diterapkan sebelum
        // lookup $rolesCI (mis. "Sales Marketing Executive" → "Sales Marketing Head").
        $roleAliases = [
            'sales marketing executive' => 'Sales Marketing Head',
            'system registered'         => 'User System Registered',
        ];

        // Peta kanonik Grade & Home Base (key: lowercase+trim → nilai kanonik).
        // Dipakai menormalkan nilai CSV agar konsisten dgn opsi dropdown UI.
        $gradeCanon = collect(\App\Models\Grade::options())
            ->mapWithKeys(fn ($n) => [mb_strtolower(trim($n)) => $n])->all();
        $homeBaseCanon = collect(\App\Enums\HomeBase::options())
            ->mapWithKeys(fn ($n) => [mb_strtolower(trim($n)) => $n])->all();
        // "User System Registered" wajib ada di employee_role_assignment agar employee
        // bisa login ke EcoSystem (AuthController cek $hasSystemAccess). Selalu di-assign
        // ke setiap employee, di samping role fungsionalnya (role_id dari kolom CSV).
        $systemRoleId = DB::table('employee_role')->where('name', 'User System Registered')->value('id');
        $imported = 0;
        $updated  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue;

            $eci = $get('eci');

            // Pencocokan employee memakai FULL NAME (first + last), bukan ECI.
            // Minimal salah satu nama wajib ada agar bisa dicocokkan.
            $firstName = $get('first_name');
            $lastName  = $get('last_name');
            $fullName  = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
            if ($fullName === '') {
                $errors[] = "Baris {$rowNum}: Nama (First/Last Name) wajib diisi — dipakai untuk mencocokkan employee";
                continue;
            }

            // Kolom "role" bisa berisi BANYAK role dipisah koma
            // (mis. "EC Director, EC User, User System Registered"). Semua role
            // valid di-assign ke employee_role_assignment; role fungsional pertama
            // (selain "User System Registered") jadi employee.role_id (primary).
            $roleRaw = $get('role');
            $roleIds = [];          // semua role valid (untuk assignment)
            $roleId  = null;        // primary → employee.role_id
            if ($roleRaw !== null) {
                $unknownRoles = [];
                foreach (array_filter(array_map('trim', explode(',', $roleRaw)), fn ($n) => $n !== '') as $rn) {
                    $key = mb_strtolower($rn);
                    if (isset($roleAliases[$key])) $key = mb_strtolower($roleAliases[$key]);
                    $rid = $rolesCI[$key] ?? null;
                    if ($rid) {
                        $roleIds[] = $rid;
                        if ($roleId === null && $rid !== $systemRoleId) $roleId = $rid;
                    } else {
                        $unknownRoles[] = $rn;
                    }
                }
                $roleIds = array_values(array_unique($roleIds));
                // Bila satu-satunya role valid adalah "User System Registered",
                // jadikan ia primary agar baris tetap punya role_id.
                if ($roleId === null && $roleIds) $roleId = $roleIds[0];
                if ($unknownRoles) {
                    $errors[] = "[Peringatan] Baris {$rowNum}: Role tidak dikenali (dilewati): " . implode(', ', $unknownRoles);
                }
            }

            $isActive = $get('status') !== null
                ? (strtolower($get('status')) === 'active' ? 1 : 0)
                : 1;

            // Normalisasi Home Base & Grade ke nilai kanonik (case-insensitive).
            // Tak dikenali → tetap disimpan apa adanya + peringatan non-fatal
            // (UI tetap menampilkan nilai mentah, tapi tak terhubung ke daftar).
            $homeBase = $get('home_base');
            if ($homeBase !== null) {
                $key = mb_strtolower(trim($homeBase));
                if (isset($homeBaseCanon[$key])) {
                    $homeBase = $homeBaseCanon[$key];
                } else {
                    $errors[] = "[Peringatan] Baris {$rowNum} ({$fullName}): Home Base '{$homeBase}' tidak dikenali — tersimpan apa adanya";
                }
            }

            $grade = $get('grade');
            if ($grade !== null) {
                $key = mb_strtolower(trim($grade));
                if (isset($gradeCanon[$key])) {
                    $grade = $gradeCanon[$key];
                } else {
                    // Fallback: CSV sering pakai bentuk singkat — bisa kata AWAL
                    // ("Senior" → "Senior Consultant", "Management" → "Management
                    // Trainee") MAUPUN kata AKHIR ("Trainee" → "Management Trainee").
                    // Cocokkan bila nilai = prefix/suffix (per-kata) dari tepat SATU
                    // grade kanonik (ambigu/tak ada → biarkan + peringatan).
                    $matches = [];
                    foreach ($gradeCanon as $canonLower => $canonName) {
                        if (str_starts_with($canonLower, $key . ' ')
                            || str_ends_with($canonLower, ' ' . $key)) {
                            $matches[] = $canonName;
                        }
                    }
                    if (count($matches) === 1) {
                        $grade = $matches[0];
                    } else {
                        $errors[] = "[Peringatan] Baris {$rowNum} ({$fullName}): Grade '{$grade}' tidak dikenali — tersimpan apa adanya, tidak terhubung ke daftar Grade";
                    }
                }
            }

            $basicData = array_filter([
                'title'              => $get('title'),
                'first_name'         => $firstName,
                'last_name'          => $lastName,
                // search_term: kolom pencarian, samakan dgn create-dari-website
                'search_term_1'      => $firstName ? strtoupper($firstName) : null,
                'search_term_2'      => $lastName ? strtoupper($lastName) : null,
                'nick_name'          => $get('nick_name'),
                'gender'             => $get('gender'),
                'religion'           => $get('religion'),
                'birth_date'         => $this->normalizeDate($get('birth_date')),
                'birth_place'        => $get('birth_place'),
                'marital_status'     => $get('marital_status'),
                'personnel_area'     => $get('personnel_area'),
                'personnel_subarea'  => $get('personnel_subarea'),
                'employee_group'     => $get('employee_group'),
                'employee_subgroup'  => $get('employee_subgroup'),
                'position'           => $get('position'),
                'division'           => $get('division'),
                'department'         => $get('department'),
                'home_base'          => $homeBase,
                'grade'              => $grade,
                // Internal/External diturunkan dari home_base ("Others" → External).
                'employee_type'      => \App\Models\EmployeeBasicData::deriveEmployeeType($homeBase),
                'since_date'         => $this->normalizeDate($get('since_date')),
            ], fn($v) => $v !== null);

            $nik       = $get('nik');
            $cellPhone = $this->normalizePhone($get('cell_phone'));

            try {
                DB::beginTransaction();

                // Cocokkan berdasarkan FULL NAME (case-insensitive). Butuh basic_data,
                // sehingga employee tanpa nama tidak akan ke-match (akan jadi baru).
                $existing = DB::table('employee as e')
                    ->join('employee_basic_data as b', 'e.employee_id', '=', 'b.employee_id')
                    ->whereRaw("LOWER(TRIM(CONCAT(COALESCE(b.first_name,''), ' ', COALESCE(b.last_name,'')))) = ?", [mb_strtolower($fullName)])
                    ->orderBy('e.employee_id') // deterministik bila ada nama kembar
                    ->select('e.employee_id', 'e.eci')
                    ->first();

                // Fallback: kalau nama tak ketemu (mis. employee lama yang punya baris
                // employee + ECI tapi belum punya basic_data), cocokkan via ECI agar
                // tidak salah dianggap baru lalu kena guard "ECI sudah dipakai".
                if (!$existing && $eci) {
                    $existing = DB::table('employee')->where('eci', $eci)
                        ->select('employee_id', 'eci')->first();
                }

                $email = $get('email');

                // Email auth WAJIB unik. Bila email CSV sudah dipakai akun lain
                // (mis. email berpola nama-depan yang kebetulan sama), jangan pakai
                // untuk baris ini — buat employee tanpa email + beri peringatan,
                // agar import tetap jalan (admin isi/perbaiki email manual nanti).
                $emailForAuth  = $email;
                $emailConflict = false;
                if ($email) {
                    $taken = DB::table('auth_users')->where('email', $email)
                        ->when($existing, fn ($q) => $q->where('employee_id', '!=', $existing->employee_id))
                        ->exists();
                    if ($taken) { $emailForAuth = null; $emailConflict = true; }
                }

                if ($existing) {
                    // ── UPDATE: full name sama → update field-fieldnya ──
                    $empUpdate = ['is_active' => $isActive, 'updated_at' => now()];

                    // ECI dari CSV adalah format terbaru & menjadi acuan ke depan →
                    // timpa ECI lama bila berbeda. Username login (auth_users) ikut
                    // disinkronkan di bawah. Skip + warn bila ECI sudah dipakai
                    // employee LAIN (cegah pelanggaran unique).
                    $syncEci = null;
                    if ($eci && $eci !== $existing->eci) {
                        $eciTaken = DB::table('employee')->where('eci', $eci)
                            ->where('employee_id', '!=', $existing->employee_id)->exists();
                        if ($eciTaken) {
                            $errors[] = "[Peringatan] Baris {$rowNum} ({$fullName}): ECI '{$eci}' sudah dipakai employee lain — ECI lama '{$existing->eci}' dipertahankan";
                        } else {
                            $empUpdate['eci'] = $eci;
                            $syncEci = $eci;
                        }
                    }

                    DB::table('employee')->where('employee_id', $existing->employee_id)->update($empUpdate);

                    // Pastikan SEMUA role dari CSV + "User System Registered" ter-assign
                    $assignRoleIds = array_values(array_unique(array_filter(array_merge($roleIds, [$systemRoleId]))));
                    if ($assignRoleIds) {
                        DB::table('employee_role_assignment')->insertOrIgnore(
                            array_map(fn ($rid) => [
                                'employee_id' => $existing->employee_id,
                                'role_id'     => $rid,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ], $assignRoleIds)
                        );
                    }

                    if ($basicData) {
                        $basicData['updated_at'] = now();
                        if (DB::table('employee_basic_data')->where('employee_id', $existing->employee_id)->exists()) {
                            DB::table('employee_basic_data')
                                ->where('employee_id', $existing->employee_id)
                                ->update($basicData);
                        } else {
                            // employee lama tanpa basic_data (mis. ke-match via ECI) → buat
                            $basicData['employee_id'] = $existing->employee_id;
                            $basicData['created_at']  = now();
                            DB::table('employee_basic_data')->insert($basicData);
                        }
                    }

                    if ($nik) {
                        $this->upsertNik($existing->employee_id, $nik);
                    }

                    if ($cellPhone) {
                        $this->upsertCellPhone($existing->employee_id, $cellPhone);
                    }

                    // Email CSV juga disimpan ke email_work alamat primary agar
                    // tampil di field "Email (Work)" view (auth_users.email tetap
                    // sumber reset password). Pakai $email mentah: meski bentrok
                    // untuk auth (unik), email_work tak punya constraint unik.
                    if ($email) {
                        $this->upsertEmailWork($existing->employee_id, $email);
                    }

                    // Pastikan akun login ada & konsisten dgn default import.
                    $authUser = DB::table('auth_users')->where('employee_id', $existing->employee_id)->first();
                    if (!$authUser) {
                        // Belum ada akun → buat dgn password default (is_already_cp=false)
                        DB::table('auth_users')->insert([
                            'employee_id'   => $existing->employee_id,
                            'customer_id'   => null,
                            'username'      => $syncEci ?? $existing->eci,
                            'email'         => $emailForAuth,
                            'phone'         => null,
                            'password'      => \Illuminate\Support\Facades\Hash::make(self::DEFAULT_IMPORT_PASSWORD, ['rounds' => 8]),
                            'is_active'     => $isActive,
                            'is_already_cp' => false,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                        if ($emailConflict) {
                            $errors[] = "[Peringatan] Baris {$rowNum} ({$fullName}): email '{$email}' sudah dipakai akun lain — employee tetap dibuat tapi TANPA email, isi manual nanti";
                        }
                    } else {
                        $authUpdate = ['updated_at' => now()];
                        if ($emailForAuth && empty($authUser->email)) {
                            $authUpdate['email'] = $emailForAuth;
                        }
                        // Sinkronkan username login dgn ECI baru. Skip + warn bila
                        // username sudah dipakai akun lain (cegah pelanggaran unique).
                        if ($syncEci && $syncEci !== $authUser->username) {
                            $usernameTaken = DB::table('auth_users')->where('username', $syncEci)
                                ->where('id', '!=', $authUser->id)->exists();
                            if ($usernameTaken) {
                                $errors[] = "[Peringatan] Baris {$rowNum} ({$fullName}): username login '{$syncEci}' sudah dipakai akun lain — username lama '{$authUser->username}' dipertahankan";
                            } else {
                                $authUpdate['username'] = $syncEci;
                            }
                        }
                        // Reset ke password default HANYA bila employee belum pernah
                        // set-password sendiri (is_already_cp=false). Jika sudah pernah,
                        // password pribadinya tidak boleh ditimpa.
                        if (!$authUser->is_already_cp) {
                            $authUpdate['password'] = \Illuminate\Support\Facades\Hash::make(self::DEFAULT_IMPORT_PASSWORD, ['rounds' => 8]);
                        }
                        DB::table('auth_users')->where('employee_id', $existing->employee_id)->update($authUpdate);
                    }

                    DB::commit();
                    $updated++;
                } else {
                    // ── CREATE: employee baru ──
                    if (!$roleId) {
                        DB::rollBack();
                        $errors[] = "Baris {$rowNum} ({$fullName}): employee baru tapi Role tidak valid — baris dilewati";
                        continue;
                    }
                    if (!$eci) {
                        DB::rollBack();
                        $errors[] = "Baris {$rowNum} ({$fullName}): Kolom 'ECI' wajib diisi untuk employee baru";
                        continue;
                    }
                    if (DB::table('employee')->where('eci', $eci)->exists()) {
                        DB::rollBack();
                        $errors[] = "Baris {$rowNum} ({$fullName}): ECI '{$eci}' sudah dipakai employee lain — baris dilewati";
                        continue;
                    }
                    // Email kosong di CSV → employee tetap dibuat, auth_users.email = null
                    // (admin bisa isi manual nanti). $emailForAuth sudah null bila $email null.

                    $employeeId = DB::table('employee')->insertGetId([
                        'eci'        => $eci,
                        'is_active'  => $isActive,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Role assignment — SEMUA role dari CSV + "User System
                    // Registered" (wajib untuk akses login).
                    $assignRoleIds = array_values(array_unique(array_filter(array_merge($roleIds, [$systemRoleId]))));
                    DB::table('employee_role_assignment')->insertOrIgnore(
                        array_map(fn ($rid) => [
                            'employee_id' => $employeeId,
                            'role_id'     => $rid,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ], $assignRoleIds)
                    );

                    // Auth account — password default = initial. is_already_cp=false:
                    // saat login pertama sistem kirim email link set-password.
                    DB::table('auth_users')->insert([
                        'employee_id'   => $employeeId,
                        'customer_id'   => null,
                        'username'      => $eci,
                        'email'         => $emailForAuth,
                        'phone'         => null,
                        'password'      => \Illuminate\Support\Facades\Hash::make(self::DEFAULT_IMPORT_PASSWORD, ['rounds' => 8]),
                        'is_active'     => $isActive,
                        'is_already_cp' => false,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                    if ($emailConflict) {
                        $errors[] = "Baris {$rowNum} ({$fullName}): email '{$email}' sudah dipakai akun lain — akun dibuat TANPA email, isi manual nanti";
                    }

                    if ($basicData) {
                        $basicData['employee_id'] = $employeeId;
                        $basicData['created_at']  = now();
                        $basicData['updated_at']  = now();
                        DB::table('employee_basic_data')->insert($basicData);
                    }

                    if ($nik) {
                        $this->upsertNik($employeeId, $nik);
                    }

                    if ($cellPhone) {
                        $this->upsertCellPhone($employeeId, $cellPhone);
                    }

                    // Tampilkan email di field "Email (Work)" view (lihat catatan
                    // di jalur UPDATE). auth_users.email tetap sumber reset password.
                    if ($email) {
                        $this->upsertEmailWork($employeeId, $email);
                    }

                    DB::commit();
                    $imported++;
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Baris {$rowNum} ({$fullName}): " . $e->getMessage();
            }
        }

        fclose($handle);

        Log::info('AdminBackupController: employee import', [
            'imported' => $imported, 'updated' => $updated, 'errors' => count($errors),
            'by'       => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Import selesai: {$imported} ditambahkan, {$updated} diperbarui" . (count($errors) ? ', ' . count($errors) . ' error' : ''),
            'imported' => $imported,
            'updated'  => $updated,
            'errors'   => $errors,
        ]);
    }

    /**
     * Normalisasi berbagai format tanggal ke 'Y-m-d' (format yang diterima MySQL DATE).
     * Menangani: YYYY-MM-DD, YYYY/MM/DD, M/D/YYYY, D/M/YYYY (Excel/US), dengan dash atau slash.
     * Heuristik M/D vs D/M: jika salah satu bagian > 12 maka itu pasti hari; bila ambigu
     * (keduanya <= 12) diasumsikan M/D/YYYY (format yang dipakai file sumber). Nilai yang
     * tidak bisa diparse dikembalikan apa adanya agar tervalidasi/terlaporkan sebagai error.
     */
    private function normalizeDate(?string $val): ?string
    {
        if ($val === null) return null;
        $val = trim($val);
        if ($val === '') return null;

        // Sudah ISO: YYYY-MM-DD atau YYYY/MM/DD
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $val, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Tahun di akhir: a/b/YYYY (slash, dash, atau titik)
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $val, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $year = (int) $m[3];

            if ($a > 12 && $b <= 12) {            // D/M/Y pasti
                $day = $a; $month = $b;
            } elseif ($b > 12 && $a <= 12) {      // M/D/Y pasti
                $month = $a; $day = $b;
            } else {                              // ambigu → asumsi D/M/Y (format Indonesia)
                $day = $a; $month = $b;
            }

            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Fallback terakhir: serahkan ke strtotime; kalau gagal kembalikan nilai asli.
        $ts = strtotime($val);
        return $ts !== false ? date('Y-m-d', $ts) : $val;
    }

    /**
     * Normalisasi nomor telepon dari CSV/Excel.
     * Excel sering merusak nomor panjang (HP Indonesia 11-15 digit):
     *  - Kolom berformat Number → angka panjang di-export sebagai notasi ilmiah
     *    ("6.28123E+12") → di sini dikembalikan ke string integer penuh.
     *  - Excel menambah apostrof di depan ('6281...) → di-strip.
     * Catatan: bila presisi SUDAH hilang di Excel (mis. "6281230000000"),
     * server tidak bisa memulihkannya — kolom Phone WAJIB diformat Text di Excel
     * sebelum diisi/di-export ke CSV.
     */
    private function normalizePhone(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim($v, " \t\n\r\0\x0B'\"");
        if ($v === '') return null;

        // Notasi ilmiah (6.28123E+12) → integer penuh tanpa desimal.
        if (preg_match('/^\d(?:\.\d+)?[eE]\+?\d+$/', $v)) {
            $v = sprintf('%.0f', (float) $v);
        }

        return $v;
    }

    private function upsertNik(int $employeeId, string $nik): void
    {
        $existing = DB::table('employee_identification')
            ->where('employee_id', $employeeId)
            ->where('identification_type', 'KTP')
            ->first();

        if ($existing) {
            DB::table('employee_identification')
                ->where('identification_id', $existing->identification_id)
                ->update(['identification_number' => $nik, 'updated_at' => now()]);
        } else {
            DB::table('employee_identification')->insert([
                'employee_id'           => $employeeId,
                'identification_type'   => 'KTP',
                'identification_number' => $nik,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }

    /**
     * Simpan nomor HP ke employee_address.cell_phone pada alamat PRIMARY employee.
     * Bila employee belum punya alamat, buat alamat primary baru bertipe 'Home'
     * (mengikuti pola EmployeeAddressController: address_type wajib, satu primary).
     */
    private function upsertCellPhone(int $employeeId, string $cellPhone): void
    {
        $primary = DB::table('employee_address')
            ->where('employee_id', $employeeId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('address_id', 'asc')
            ->first();

        if ($primary) {
            DB::table('employee_address')
                ->where('address_id', $primary->address_id)
                ->update(['cell_phone' => $cellPhone, 'updated_at' => now()]);
        } else {
            DB::table('employee_address')->insert([
                'employee_id'  => $employeeId,
                'address_type' => 'Home',
                'cell_phone'   => $cellPhone,
                'is_primary'   => true,
                'is_verified'  => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    /**
     * Simpan email dari CSV ke employee_address.email_work (alamat primary).
     *
     * Email login disimpan ke auth_users.email (sumber reset/set-password),
     * tetapi field "Email (Work)" di view membaca dari employee_address.email_work.
     * Tanpa ini, email tidak terlihat di UI meski reset password berfungsi.
     * Dua-arah: edit "Email (Work)" alamat primary nanti disinkronkan balik ke
     * auth_users.email oleh EmployeeAddressController.
     */
    private function upsertEmailWork(int $employeeId, string $emailWork): void
    {
        $primary = DB::table('employee_address')
            ->where('employee_id', $employeeId)
            ->orderBy('is_primary', 'desc')
            ->orderBy('address_id', 'asc')
            ->first();

        if ($primary) {
            DB::table('employee_address')
                ->where('address_id', $primary->address_id)
                ->update(['email_work' => $emailWork, 'updated_at' => now()]);
        } else {
            DB::table('employee_address')->insert([
                'employee_id'  => $employeeId,
                'address_type' => 'Home',
                'email_work'   => $emailWork,
                'is_primary'   => true,
                'is_verified'  => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    // ── Import Customer ───────────────────────────────────────────────────────

    public function importCustomers(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'File CSV kosong atau tidak valid'], 422);
        }

        $headerMap = array_flip(array_map('trim', $rawHeaders));

        if (!isset($headerMap['Company Name'])) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Kolom "Company Name" wajib ada di CSV'], 422);
        }

        $imported = 0;
        $updated  = 0;
        $errors   = [];
        $rowNum   = 1;
        // [customer_id => parent customer code] — resolved in a second pass so a
        // parent referenced by a row above it (not yet inserted) still maps.
        $pendingParents = [];

        // CSV mungkin disimpan dalam Windows-1252/Latin-1 (mis. nama perusahaan
        // beraksen). Byte non-UTF-8 yang lolos ke array $errors akan membuat
        // response()->json() melempar "Malformed UTF-8 characters" (HTTP 500).
        // Normalisasi tiap sel ke UTF-8 saat dibaca agar aman untuk DB & JSON.
        $get = function (string $col, array $row) use ($headerMap): ?string {
            if (!isset($headerMap[$col])) return null;
            $val = $row[$headerMap[$col]] ?? '';
            if ($val !== '' && !mb_check_encoding($val, 'UTF-8')) {
                $val = mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
            }
            $val = trim($val);
            return $val !== '' ? $val : null;
        };

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue;

            $companyName = $get('Company Name', $row);
            if (!$companyName) { $errors[] = "Baris {$rowNum}: Company Name wajib diisi"; continue; }

            $customerCode = $get('Customer Code', $row);
            $email        = $get('Email', $row);
            $parentCode   = $get('Parent Customer Code', $row);
            $isActive     = $get('Status', $row) !== null
                ? (strtolower($get('Status', $row)) === 'active' ? 1 : 0)
                : 1;
            // Customer Group struktural — resolve nama ke customer_groups
            // (find-or-create). null bila kolom kosong → FK tidak disentuh saat
            // update, parity dengan mirror kolom teks yang juga di-skip saat kosong.
            $group   = $this->resolveCustomerGroup($get('Customer Group', $row));
            $groupId = $group['id'] ?? null;

            $basicData = array_filter([
                'name_1'                => $companyName,
                'name_2'               => $get('Name 2', $row),
                'title'                => $get('Title', $row),
                // Mirror nama kanonik grup (bukan nilai mentah CSV) agar konsisten
                // dengan FK customer_group_id dan dengan syncGroupNameToBasicData().
                'customer_group'       => $group['name'] ?? null,
                'customer_category'    => $get('Customer Category', $row),
                'industry_sector'      => $get('Industry Sector', $row),
                'ec_account_executive' => $get('EC Account Executive', $row),
                'sap_account_executive' => $get('SAP Account Executive', $row),
            ], fn($v) => $v !== null);

            try {
                // Cari existing: prioritas customer_code, fallback ke email
                $existing = null;
                if ($customerCode) {
                    $existing = DB::table('customer')->where('customer_code', $customerCode)->first();
                }
                if (!$existing && $email) {
                    $existing = DB::table('customer')->where('email', $email)->first();
                }

                if ($existing) {
                    $empUpdate = ['is_active' => $isActive, 'updated_at' => now()];
                    // Hanya overwrite grup bila CSV menyertakan nilai (kolom kosong
                    // = tidak mengubah grup existing).
                    if ($groupId !== null) $empUpdate['customer_group_id'] = $groupId;
                    if ($customerCode && $customerCode !== $existing->customer_code) {
                        $taken = DB::table('customer')->where('customer_code', $customerCode)
                            ->where('customer_id', '!=', $existing->customer_id)->exists();
                        if (!$taken) $empUpdate['customer_code'] = $customerCode;
                    }
                    if ($email && $email !== $existing->email) {
                        $taken = DB::table('customer')->where('email', $email)
                            ->where('customer_id', '!=', $existing->customer_id)->exists();
                        if (!$taken) $empUpdate['email'] = $email;
                        else { $errors[] = "Baris {$rowNum}: Email '{$email}' sudah dipakai customer lain"; }
                    }
                    DB::table('customer')->where('customer_id', $existing->customer_id)->update($empUpdate);

                    $basicData['updated_at'] = now();
                    $bdExists = DB::table('customer_basic_data')->where('customer_id', $existing->customer_id)->exists();
                    if ($bdExists) {
                        DB::table('customer_basic_data')->where('customer_id', $existing->customer_id)->update($basicData);
                    } else {
                        $basicData['customer_id'] = $existing->customer_id;
                        $basicData['created_at']  = now();
                        DB::table('customer_basic_data')->insert($basicData);
                    }
                    $this->upsertCustomerAddress($existing->customer_id, $get, $row);
                    if ($parentCode !== null) $pendingParents[$existing->customer_id] = $parentCode;
                    $updated++;
                } else {
                    // Generate customer_code jika tidak ada
                    if (!$customerCode) {
                        $prefix       = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $companyName), 0, 4));
                        $seq          = DB::table('customer')->where('customer_code', 'like', $prefix . '%')->count() + 1;
                        $customerCode = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
                        while (DB::table('customer')->where('customer_code', $customerCode)->exists()) {
                            $customerCode = $prefix . str_pad(++$seq, 3, '0', STR_PAD_LEFT);
                        }
                    }

                    if (DB::table('customer')->where('customer_code', $customerCode)->exists()) {
                        $errors[] = "Baris {$rowNum}: Customer Code '{$customerCode}' sudah ada — baris dilewati";
                        continue;
                    }
                    if ($email && DB::table('customer')->where('email', $email)->exists()) {
                        $errors[] = "Baris {$rowNum}: Email '{$email}' sudah terdaftar — baris dilewati";
                        continue;
                    }

                    $customerId = DB::table('customer')->insertGetId([
                        'customer_code'     => $customerCode,
                        'email'             => $email,
                        'is_active'         => $isActive,
                        'customer_group_id' => $groupId,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    $basicData['customer_id'] = $customerId;
                    $basicData['created_at']  = now();
                    $basicData['updated_at']  = now();
                    DB::table('customer_basic_data')->insert($basicData);
                    $this->upsertCustomerAddress($customerId, $get, $row);
                    if ($parentCode !== null) $pendingParents[$customerId] = $parentCode;
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        fclose($handle);

        // ── Second pass: resolve Parent Customer Code → parent_customer_id ──
        foreach ($pendingParents as $cid => $pcode) {
            $parent = DB::table('customer')->where('customer_code', $pcode)->first();
            if (!$parent) {
                $errors[] = "Parent Customer Code '{$pcode}' tidak ditemukan — parent untuk customer ID {$cid} dilewati";
                continue;
            }
            if ($parent->customer_id == $cid) {
                $errors[] = "Customer ID {$cid} tidak bisa menjadi parent dirinya sendiri";
                continue;
            }
            DB::table('customer')->where('customer_id', $cid)
                ->update(['parent_customer_id' => $parent->customer_id, 'updated_at' => now()]);
        }

        // Insurance: pesan exception DB pun bisa membawa byte non-UTF-8.
        $errors = array_map(
            fn($e) => mb_check_encoding($e, 'UTF-8') ? $e : mb_convert_encoding($e, 'UTF-8', 'Windows-1252'),
            $errors
        );

        Log::info('AdminBackupController: customer import', [
            'imported' => $imported, 'updated' => $updated, 'errors' => count($errors),
            'by'       => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Import selesai: {$imported} ditambahkan, {$updated} diperbarui" . (count($errors) ? ', ' . count($errors) . ' error' : ''),
            'imported' => $imported,
            'updated'  => $updated,
            'errors'   => $errors,
        ]);
    }

    /**
     * Resolve nama "Customer Group" dari CSV ke customer_groups (find-or-create),
     * meniru pola backfill migration customer_groups. Match case-insensitive agar
     * tidak menabrak UNIQUE constraint pada kolom `name` (mis. "bumn" vs "BUMN").
     * Mengembalikan ['id' => int, 'name' => string] dengan nama kanonik grup
     * (untuk di-mirror ke kolom teks lama), atau null bila kolom kosong.
     */
    private function resolveCustomerGroup(?string $name): ?array
    {
        if ($name === null || trim($name) === '') return null;
        $name = trim($name);

        $existing = DB::table('customer_groups')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) return ['id' => $existing->id, 'name' => $existing->name];

        $id = DB::table('customer_groups')->insertGetId([
            'name'       => $name,
            'code'       => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10)) ?: null,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'name' => $name];
    }

    /**
     * Buat/sinkron 1 record alamat utama dari kolom alamat CSV import customer.
     * Idempotent: bila customer sudah punya alamat, baris pertama di-update;
     * jika belum ada, baris baru disisipkan. Tidak melakukan apa-apa bila
     * seluruh kolom alamat kosong.
     *
     * @param callable $get  closure(string $col, array $row): ?string
     */
    private function upsertCustomerAddress(int $customerId, callable $get, array $row): void
    {
        $address = array_filter([
            'building_name'       => $get('Nama Gedung/Tempat', $row),
            'full_address'        => $get('Alamat Lengkap', $row),
            'street'              => $get('Street', $row),
            'postal_code'         => $get('Postal Code', $row),
            'country'             => $get('Country', $row),
            'region'              => $get('Region/Province', $row),
            'city'                => $get('City', $row),
            'district'            => $get('District', $row),
            'rural_urban_village' => $get('Urban Villages', $row),
            'telephone'           => $get('Phone', $row),
            'fax'                 => $get('Fax', $row),
        ], fn($v) => $v !== null);

        // Semua kolom alamat kosong → tidak ada yang perlu disimpan
        if (empty($address)) return;

        $existing = DB::table('customer_address')->where('customer_id', $customerId)
            ->orderBy('address_id')->first();

        if ($existing) {
            $address['updated_at'] = now();
            DB::table('customer_address')->where('address_id', $existing->address_id)->update($address);
        } else {
            $address['customer_id']  = $customerId;
            $address['address_type'] = 'Primary';
            $address['created_at']   = now();
            $address['updated_at']   = now();
            DB::table('customer_address')->insert($address);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findMysqldump(): ?string
    {
        $candidates = [
            'mysqldump',                            // Linux/Mac PATH
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe', // Windows XAMPP
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
        ];

        foreach ($candidates as $candidate) {
            if (PHP_OS_FAMILY === 'Windows') {
                if (str_ends_with($candidate, '.exe') && file_exists($candidate)) return $candidate;
                // Check PATH via where
                exec('where ' . escapeshellarg($candidate) . ' 2>NUL', $out, $code);
                if ($code === 0 && !empty($out)) return $candidate;
            } else {
                exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out)) return $out[0];
                if (file_exists($candidate)) return $candidate;
            }
        }
        return null;
    }

    // ── Template Tickets ──────────────────────────────────────────────────────

    public function templateTickets()
    {
        if (!$this->assertAdmin()) abort(403);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            [
                'Tiket', 'Description', 'Date', 'Customer', 'End Customer',
                'PIC', 'Priority', 'Scale', 'Status', 'Type',
                'Assign Delivery', 'Customer Mandays', 'Progress',
                'Target Respon Time (Hour)', 'Respon Time (Hour)', 'Respon Time Status',
                'Target Resolution Time', 'Due Date/Time Resolution Time',
                'Resolution Time', 'Resolution Time Status',
            ],
            [
                '100000001', 'Login page not responding', '01 Jun 2026', 'PT Example Tbk', '',
                'John Doe', 'High', 'Simple', 'Inprocess', 'Incident',
                '', '', '',
                '', '', '',
                '', '2026-06-30',
                '', '',
            ],
        ]);

        $writer = new Xlsx($spreadsheet);
        return response()->stream(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="tickets_import_template.xlsx"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    // ── Import Ticket (CSV) ───────────────────────────────────────────────────

    public function importTickets(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        set_time_limit(300);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:20480']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'File CSV kosong atau tidak valid'], 422);
        }

        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $rawHeaders);

        $aliasMap = [
            'ticket_number' => ['tiket', 'ticket', 'ticket_number', 'ticket number', 'no tiket', 'no. tiket'],
            'description'   => ['description', 'deskripsi', 'subject', 'keterangan'],
            'date'          => ['date', 'tanggal', 'start date', 'start_date'],
            'customer'      => ['customer', 'customer_code', 'customer code'],
            'end_customer'  => ['end customer', 'end_customer', 'end_customer_code'],
            'status'        => ['status'],
            'priority'      => ['priority', 'ticket_priority', 'prioritas'],
            'scale'         => ['scale', 'skala'],
            'type'          => ['type', 'ticket type', 'ticket_type', 'tipe'],
            'pic'           => ['pic'],
            'ticket_lead'   => ['ticket lead', 'ticket_lead', 'lead'],
            'end_date'      => ['due date/time resolution time', 'end_date', 'due date', 'due_date'],
        ];

        // Last-match wins: bila ada kolom duplikat (misal "Priority","Priority"),
        // kita inginkan kolom TERAKHIR yang cocok agar format lama tidak dipilih.
        $colIndex = [];
        foreach ($aliasMap as $field => $aliases) {
            foreach ($headers as $i => $h) {
                if (in_array($h, $aliases, true)) { $colIndex[$field] = $i; }
            }
        }

        if (!isset($colIndex['ticket_number'])) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Kolom "Tiket" (ticket number) tidak ditemukan di CSV'], 422);
        }

        $statusMap = [
            'open'                    => 'open',
            'inprocess'               => 'inprocess',
            'in process'              => 'inprocess',
            'waiting on customer'     => 'waiting_on_customer',
            'waiting_on_customer'     => 'waiting_on_customer',
            'waiting on 3rd party'    => 'waiting_on_3rd_party',
            'waiting_on_3rd_party'    => 'waiting_on_3rd_party',
            'waiting to confirmation' => 'waiting_to_confirmation',
            'waiting_to_confirmation' => 'waiting_to_confirmation',
            'hold'                    => 'hold',
            'cancelled'               => 'cancelled',
            'canceled'                => 'cancelled',
            'cancel'                  => 'cancelled',
            'closed'                  => 'closed',
            'close'                   => 'closed',
        ];
        $validPriorities = ['Very High', 'High', 'Medium', 'Low'];
        $validScales     = ['Simple', 'Medium', 'Complex'];
        $validTypes      = ['Incident', 'Service Request', 'Change Request', 'Consult'];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors  = [];
        $rowNum  = 1;

        // Lookup employee by display name (first_name or full name concat)
        $lookupEmployeeByName = function (string $name): ?object {
            $lower = strtolower(trim($name));
            return DB::table('employee as e')
                ->join('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
                ->where(function ($q) use ($lower) {
                    $q->whereRaw("LOWER(TRIM(ebd.first_name)) = ?", [$lower])
                      ->orWhereRaw("LOWER(TRIM(CONCAT(ebd.first_name, ' ', COALESCE(ebd.last_name, '')))) = ?", [$lower]);
                })
                ->select('e.employee_id', 'ebd.first_name')
                ->first();
        };

        // Split a ticket lead cell into individual names (separator: comma or period), skip '-' / empty
        $parseLeadNames = function (string $raw): array {
            return array_values(array_filter(
                array_map('trim', preg_split('/[,.]/', $raw)),
                fn($n) => $n !== '' && $n !== '-'
            ));
        };

        // Upsert a ticket member (insert if absent, reactivate if soft-deleted)
        $upsertMember = function (int $ticketId, int $empId): void {
            $existing = DB::table('ticket_member')
                ->where('ticket_id', $ticketId)
                ->where('employee_id', $empId)
                ->first();
            if (!$existing) {
                DB::table('ticket_member')->insert([
                    'ticket_id'   => $ticketId,
                    'employee_id' => $empId,
                    'is_active'   => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            } elseif (!$existing->is_active) {
                DB::table('ticket_member')
                    ->where('ticket_id', $ticketId)
                    ->where('employee_id', $empId)
                    ->update(['is_active' => true, 'updated_at' => now()]);
            }
        };

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Nilai error Excel (#N/A, #REF!, dll.) → kosongkan agar diperlakukan sebagai tidak ada
            $row = array_map(fn($v) => ($v !== null && preg_match('/^#/', ltrim($v))) ? '' : $v, $row);

            $ticketNumber = trim($row[$colIndex['ticket_number']] ?? '');
            if ($ticketNumber === '' || $ticketNumber === '-') { $skipped++; continue; }

            $ticket = DB::table('ticket')
                ->where('ticket_number', $ticketNumber)
                ->whereNull('deleted_at')
                ->first();

            if (!$ticket) {
                // ── CREATE: ticket tidak ditemukan → buat baru ──
                $rawDescriptionCreate = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : '';
                $rawCustomerCreate    = isset($colIndex['customer'])    ? trim($row[$colIndex['customer']]    ?? '') : '';
                $rawPriorityCreate    = isset($colIndex['priority'])    ? trim($row[$colIndex['priority']]    ?? '') : '';
                $rawTypeCreate        = isset($colIndex['type'])        ? trim($row[$colIndex['type']]        ?? '') : '';

                $missingCreate = [];
                if ($rawDescriptionCreate === '' || $rawDescriptionCreate === '-') $missingCreate[] = 'Description';
                if ($rawPriorityCreate    === '' || $rawPriorityCreate    === '-') $missingCreate[] = 'Priority';
                if ($rawTypeCreate        === '' || $rawTypeCreate        === '-') $missingCreate[] = 'Type';
                if ($rawCustomerCreate    === '' || $rawCustomerCreate    === '-') $missingCreate[] = 'Customer';

                if ($missingCreate) {
                    $errors[] = "Row {$rowNum} (#{$ticketNumber}): Ticket tidak ditemukan dan tidak dapat dibuat — field wajib kosong: " . implode(', ', $missingCreate);
                    $skipped++;
                    continue;
                }

                // Resolve customer → customer_id
                $customerCreate = DB::table('customer as c')
                    ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                    ->where(function ($q) use ($rawCustomerCreate) {
                        $q->where('c.customer_code', $rawCustomerCreate)->orWhere('cbd.name_1', $rawCustomerCreate);
                    })
                    ->select('c.customer_id')
                    ->first();

                if (!$customerCreate) {
                    $errors[] = "Row {$rowNum} (#{$ticketNumber}): Customer '{$rawCustomerCreate}' tidak ditemukan — ticket tidak dapat dibuat";
                    $skipped++;
                    continue;
                }

                // Validate priority
                $priorityCreate = null;
                foreach ($validPriorities as $vp) {
                    if (strcasecmp($rawPriorityCreate, $vp) === 0) { $priorityCreate = $vp; break; }
                }
                if (!$priorityCreate) {
                    $errors[] = "Row {$rowNum} (#{$ticketNumber}): Priority '{$rawPriorityCreate}' tidak valid (diterima: " . implode(', ', $validPriorities) . ") — ticket tidak dapat dibuat";
                    $skipped++;
                    continue;
                }

                // Validate type
                $typeCreate = null;
                foreach ($validTypes as $vt) {
                    if (strcasecmp($rawTypeCreate, $vt) === 0) { $typeCreate = $vt; break; }
                }
                if (!$typeCreate) {
                    $errors[] = "Row {$rowNum} (#{$ticketNumber}): Type '{$rawTypeCreate}' tidak valid (diterima: " . implode(', ', $validTypes) . ") — ticket tidak dapat dibuat";
                    $skipped++;
                    continue;
                }

                // Status (default: open)
                $statusCreate = 'open';
                if (isset($colIndex['status'])) {
                    $rawSC = strtolower(trim($row[$colIndex['status']] ?? ''));
                    if ($rawSC !== '' && $rawSC !== '-') $statusCreate = $statusMap[$rawSC] ?? 'open';
                }

                // Scale (optional)
                $scaleCreate = null;
                if (isset($colIndex['scale'])) {
                    $rawSc = trim($row[$colIndex['scale']] ?? '');
                    if ($rawSc !== '' && $rawSc !== '-') {
                        foreach ($validScales as $vs) {
                            if (strcasecmp($rawSc, $vs) === 0) { $scaleCreate = $vs; break; }
                        }
                    }
                }

                // PIC (optional)
                $picCreate = null;
                if (isset($colIndex['pic'])) {
                    $rawPC = trim($row[$colIndex['pic']] ?? '');
                    if ($rawPC !== '' && $rawPC !== '-') $picCreate = $rawPC;
                }

                // Ticket Lead (optional) — first name → lead, rest → members
                $ticketLeadIdCreate  = null;
                $extraMemberIdsCreate = [];
                if (isset($colIndex['ticket_lead'])) {
                    $rawLead = trim($row[$colIndex['ticket_lead']] ?? '');
                    if ($rawLead !== '' && $rawLead !== '-') {
                        $leadNames = $parseLeadNames($rawLead);
                        $firstLead = true;
                        foreach ($leadNames as $leadName) {
                            $emp = $lookupEmployeeByName($leadName);
                            if (!$emp) {
                                $errors[] = "[Peringatan] Row {$rowNum} (#{$ticketNumber}): Ticket Lead '{$leadName}' tidak ditemukan — dilewati";
                                continue;
                            }
                            if ($firstLead) {
                                $ticketLeadIdCreate = $emp->employee_id;
                                $picCreate          = $leadName; // override pic with lead name
                                $firstLead          = false;
                            } else {
                                $extraMemberIdsCreate[] = $emp->employee_id;
                            }
                        }
                    }
                }

                // Start date (optional — kolom 'Date')
                $startDateCreate = null;
                if (isset($colIndex['date'])) {
                    $rawDC = trim($row[$colIndex['date']] ?? '');
                    if ($rawDC !== '' && $rawDC !== '-') $startDateCreate = $this->normalizeDate($rawDC);
                }

                // End date / due date (optional)
                $endDateCreate = null;
                if (isset($colIndex['end_date'])) {
                    $rawEdC = trim($row[$colIndex['end_date']] ?? '');
                    if ($rawEdC !== '' && $rawEdC !== '-') $endDateCreate = $this->normalizeDate($rawEdC);
                }

                // End customer (optional)
                $endCustomerIdCreate = null;
                if (isset($colIndex['end_customer'])) {
                    $rawEc = trim($row[$colIndex['end_customer']] ?? '');
                    if ($rawEc !== '' && $rawEc !== '-') {
                        $endCustomerIdCreate = DB::table('customer as c')
                            ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                            ->where(function ($q) use ($rawEc) {
                                $q->where('c.customer_code', $rawEc)->orWhere('cbd.name_1', $rawEc);
                            })
                            ->value('c.customer_id');
                        if (!$endCustomerIdCreate) {
                            $errors[] = "[Peringatan] Row {$rowNum} (#{$ticketNumber}): End Customer '{$rawEc}' tidak ditemukan — diabaikan";
                        }
                    }
                }

                try {
                    DB::table('ticket')->insert([
                        'ticket_number'      => $ticketNumber,
                        'customer_id'        => $customerCreate->customer_id,
                        'end_customer_id'    => $endCustomerIdCreate,
                        'description'        => $rawDescriptionCreate,
                        'ticket_priority'    => $priorityCreate,
                        'ticket_type'        => $typeCreate,
                        'scale'              => $scaleCreate,
                        'status'             => $statusCreate,
                        'pic'                => $picCreate ?? 'Helpdesk',
                        'ticket_lead_id'     => $ticketLeadIdCreate,
                        'start_date'         => $startDateCreate,
                        'end_date'           => $endDateCreate,
                        'channel'            => 'imported',
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                    $created++;

                    // Add extra members from ticket_lead column (2nd, 3rd, ... names)
                    if (!empty($extraMemberIdsCreate)) {
                        $newTicketId = DB::table('ticket')->where('ticket_number', $ticketNumber)->value('ticket_id');
                        if ($newTicketId) {
                            foreach ($extraMemberIdsCreate as $memberId) {
                                $upsertMember($newTicketId, $memberId);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum} (#{$ticketNumber}): " . $e->getMessage();
                    $skipped++;
                }
                continue;
            }

            // Validate customer existence if the column is present and filled
            if (isset($colIndex['customer'])) {
                $rawCustomer = trim($row[$colIndex['customer']] ?? '');
                if ($rawCustomer !== '' && $rawCustomer !== '-') {
                    $customerExists = DB::table('customer as c')
                        ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                        ->where(function ($q) use ($rawCustomer) {
                            $q->where('c.customer_code', $rawCustomer)
                              ->orWhere('cbd.name_1', $rawCustomer);
                        })
                        ->exists();

                    if (!$customerExists) {
                        $errors[] = "Row {$rowNum} (#{$ticketNumber}): Customer '{$rawCustomer}' tidak ditemukan di master customer — baris dilewati";
                        $skipped++;
                        continue;
                    }
                }
            }

            $updateData = [];

            if (isset($colIndex['description'])) {
                $raw = trim($row[$colIndex['description']] ?? '');
                if ($raw !== '' && $raw !== '-') {
                    $updateData['description'] = $raw;
                }
            }

            if (isset($colIndex['status'])) {
                $raw = strtolower(trim($row[$colIndex['status']] ?? ''));
                if ($raw !== '' && $raw !== '-') {
                    $mapped = $statusMap[$raw] ?? null;
                    if ($mapped) {
                        $updateData['status'] = $mapped;
                    } else {
                        $errors[] = "Row {$rowNum} (#{$ticketNumber}): Invalid status '{$row[$colIndex['status']]}'";
                    }
                }
            }

            if (isset($colIndex['priority'])) {
                $raw = trim($row[$colIndex['priority']] ?? '');
                if ($raw !== '' && $raw !== '-') {
                    $matched = null;
                    foreach ($validPriorities as $vp) {
                        if (strcasecmp($raw, $vp) === 0) { $matched = $vp; break; }
                    }
                    if ($matched) {
                        $updateData['ticket_priority'] = $matched;
                    } else {
                        $errors[] = "Row {$rowNum} (#{$ticketNumber}): Invalid priority '{$raw}' (accepted: Very High, High, Medium, Low)";
                    }
                }
            }

            if (isset($colIndex['scale'])) {
                $raw = trim($row[$colIndex['scale']] ?? '');
                if ($raw !== '' && $raw !== '-') {
                    $matched = null;
                    foreach ($validScales as $vs) {
                        if (strcasecmp($raw, $vs) === 0) { $matched = $vs; break; }
                    }
                    if ($matched) {
                        $updateData['scale'] = $matched;
                    } else {
                        $errors[] = "Row {$rowNum} (#{$ticketNumber}): Invalid scale '{$raw}' (accepted: Simple, Medium, Complex)";
                    }
                }
            }

            if (isset($colIndex['type'])) {
                $raw = trim($row[$colIndex['type']] ?? '');
                if ($raw !== '' && $raw !== '-') {
                    $matched = null;
                    foreach ($validTypes as $vt) {
                        if (strcasecmp($raw, $vt) === 0) { $matched = $vt; break; }
                    }
                    if ($matched) {
                        $updateData['ticket_type'] = $matched;
                    } else {
                        $errors[] = "Row {$rowNum} (#{$ticketNumber}): Invalid type '{$raw}' (accepted: Incident, Service Request, Change Request, Consult)";
                    }
                }
            }

            if (isset($colIndex['pic'])) {
                $raw = trim($row[$colIndex['pic']] ?? '');
                if ($raw !== '' && $raw !== '-') {
                    $updateData['pic'] = $raw;
                }
            }

            // Ticket Lead (UPDATE) — first name → update lead + pic, rest → add as members
            $extraMemberIdsUpdate = [];
            if (isset($colIndex['ticket_lead'])) {
                $rawLead = trim($row[$colIndex['ticket_lead']] ?? '');
                if ($rawLead !== '' && $rawLead !== '-') {
                    $leadNames = $parseLeadNames($rawLead);
                    $firstLead = true;
                    foreach ($leadNames as $leadName) {
                        $emp = $lookupEmployeeByName($leadName);
                        if (!$emp) {
                            $errors[] = "[Peringatan] Row {$rowNum} (#{$ticketNumber}): Ticket Lead '{$leadName}' tidak ditemukan — dilewati";
                            continue;
                        }
                        if ($firstLead) {
                            $updateData['ticket_lead_id'] = $emp->employee_id;
                            $updateData['pic']            = $leadName;
                            $firstLead                    = false;
                        } else {
                            $extraMemberIdsUpdate[] = $emp->employee_id;
                        }
                    }
                }
            }

            if (isset($colIndex['end_date'])) {
                $raw = trim($row[$colIndex['end_date']] ?? '');
                if ($raw !== '' && $raw !== '-') {
                    $normalized = $this->normalizeDate($raw);
                    if ($normalized) {
                        $updateData['end_date'] = $normalized;
                    }
                }
            }

            if (empty($updateData)) {
                $skipped++;
                continue;
            }

            try {
                $updateData['updated_at'] = now();
                DB::table('ticket')->where('ticket_id', $ticket->ticket_id)->update($updateData);
                $updated++;

                // Add extra members from ticket_lead column (2nd, 3rd, ... names)
                foreach ($extraMemberIdsUpdate as $memberId) {
                    $upsertMember($ticket->ticket_id, $memberId);
                }
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum} (#{$ticketNumber}): " . $e->getMessage();
            }
        }

        fclose($handle);

        Log::info('AdminBackupController: ticket import', [
            'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => count($errors),
            'by'      => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Import complete: {$created} created, {$updated} updated" . ($skipped ? ", {$skipped} skipped" : '') . (count($errors) ? ', ' . count($errors) . ' error(s)' : ''),
            'created'  => $created,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }

    // ── Import Ticket Members (CSV) ───────────────────────────────────────────────

    public function importTicketMembers(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        set_time_limit(300);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:20480']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'File CSV kosong atau tidak valid'], 422);
        }

        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $rawHeaders);

        $colTicket = null;
        $colMember = null;
        $colEmpId  = null;

        foreach ($headers as $i => $h) {
            if (in_array($h, ['tiket', 'ticket', 'ticket_number', 'no tiket', 'no. tiket'])) $colTicket = $i;
            if (in_array($h, ['member', 'nama', 'nama member', 'name', 'employee']))          $colMember = $i;
            if (in_array($h, ['emp id', 'emp_id', 'eci', 'employee id', 'employee_id']))      $colEmpId  = $i;
        }

        if ($colTicket === null || $colEmpId === null) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => "Kolom wajib tidak ditemukan. Butuh: 'Tiket', 'EMP ID'"], 422);
        }

        $leadsSet     = 0;
        $membersAdded = 0;
        $skipped      = 0;
        $errors       = [];
        $rowNum       = 0;
        $seenTickets  = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            $row = array_map(fn($v) => ($v !== null && preg_match('/^#/', ltrim($v))) ? '' : $v, $row);

            $ticketNumber = trim($row[$colTicket] ?? '');
            $memberName   = $colMember !== null ? trim($row[$colMember] ?? '') : '';
            $empId        = trim($row[$colEmpId] ?? '');

            if ($ticketNumber === '' || !preg_match('/^\d+$/', $ticketNumber)) {
                $skipped++;
                continue;
            }

            if ($empId === '' || $empId === '-') {
                $skipped++;
                continue;
            }

            $employee = DB::table('employee')->where('eci', $empId)->first();
            if (!$employee) {
                $errors[] = "Row {$rowNum} (#{$ticketNumber}): Employee dengan ECI '{$empId}' tidak ditemukan";
                $skipped++;
                continue;
            }

            $ticket = DB::table('ticket')->where('ticket_number', $ticketNumber)->first();
            if (!$ticket) {
                $errors[] = "Row {$rowNum} (#{$ticketNumber}): Ticket tidak ditemukan";
                $skipped++;
                continue;
            }

            try {
                if (!isset($seenTickets[$ticketNumber])) {
                    $seenTickets[$ticketNumber] = true;
                    $picName = ($memberName !== '' && $memberName !== '-') ? $memberName : $empId;
                    DB::table('ticket')
                        ->where('ticket_id', $ticket->ticket_id)
                        ->update([
                            'ticket_lead_id' => $employee->employee_id,
                            'pic'            => $picName,
                            'updated_at'     => now(),
                        ]);
                    $leadsSet++;
                } else {
                    $exists = DB::table('ticket_member')
                        ->where('ticket_id', $ticket->ticket_id)
                        ->where('employee_id', $employee->employee_id)
                        ->exists();

                    if (!$exists) {
                        DB::table('ticket_member')->insert([
                            'ticket_id'   => $ticket->ticket_id,
                            'employee_id' => $employee->employee_id,
                            'is_active'   => true,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                        $membersAdded++;
                    } else {
                        $skipped++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum} (#{$ticketNumber}): " . $e->getMessage();
            }
        }

        fclose($handle);

        Log::info('AdminBackupController: ticket member import', [
            'leads_set'     => $leadsSet,
            'members_added' => $membersAdded,
            'skipped'       => $skipped,
            'errors'        => count($errors),
            'by'            => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->json([
            'success'       => true,
            'message'       => "Import complete: {$leadsSet} ticket lead diset, {$membersAdded} member ditambahkan" . ($skipped ? ", {$skipped} dilewati" : '') . (count($errors) ? ', ' . count($errors) . ' error(s)' : ''),
            'leads_set'     => $leadsSet,
            'members_added' => $membersAdded,
            'skipped'       => $skipped,
            'errors'        => $errors,
        ]);
    }

    // ── Template Resolution Days ──────────────────────────────────────────────────

    public function templateResolutionDays()
    {
        if (!$this->assertAdmin()) abort(403);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resolution Days');
        $sheet->fromArray([
            ['Ticket Number', 'Employee ECI', 'Module', 'Mandays', 'Additional Mandays', 'Notes'],
            ['100000001', 'ECI001', 'Finance Module', '3.5', '1.0', 'Additional work for UAT'],
            ['100000001', 'ECI002', 'Integration', '2.0', '', 'Integration testing'],
            ['100000002', 'ECI003', 'Core System', '5.0', '', ''],
        ]);
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        return response()->stream(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="resolution_days_import_template.xlsx"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    // ── Import Resolution Days ────────────────────────────────────────────────────

    public function importResolutionDays(\Illuminate\Http\Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        set_time_limit(300);
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:20480']);

        $file      = $request->file('file');
        $ext       = strtolower($file->getClientOriginalExtension());
        $allRows   = $this->readSpreadsheetFile($file->getRealPath(), $ext);

        if (!$allRows || count($allRows) < 2) {
            return response()->json(['success' => false, 'message' => 'File kosong atau tidak valid'], 422);
        }

        $rawHeaders = array_shift($allRows);
        $headerMap  = [];
        foreach ($rawHeaders as $i => $h) {
            $headerMap[strtolower(trim((string)$h))] = $i;
        }

        $aliasMap = [
            'ticket_number'      => ['ticket number', 'ticket_number', 'tiket', 'no tiket', 'no. tiket'],
            'employee_eci'       => ['employee eci', 'employee_eci', 'eci'],
            'module'             => ['module', 'modul'],
            'mandays'            => ['mandays', 'man days', 'md', 'resolution days'],
            'additional_mandays' => ['additional mandays', 'additional_mandays', 'additional md', 'tambahan md', 'additional days'],
            'notes'              => ['notes', 'catatan', 'keterangan'],
        ];

        $colIdx = [];
        foreach ($aliasMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) { $colIdx[$field] = $headerMap[$alias]; break; }
            }
        }

        if (!isset($colIdx['ticket_number']) || !isset($colIdx['employee_eci']) || !isset($colIdx['mandays'])) {
            return response()->json(['success' => false, 'message' => 'Kolom wajib tidak ditemukan: "Ticket Number", "Employee ECI", "Mandays"'], 422);
        }

        $get = function (string $field, array $row) use ($colIdx): ?string {
            if (!isset($colIdx[$field])) return null;
            $val = trim((string)($row[$colIdx[$field]] ?? ''));
            return $val !== '' ? $val : null;
        };

        // Admin employee_id for proposed_by
        $adminEmpId = session('user.id');

        // Group rows by ticket_number so we process one consultant_mandays per ticket
        $grouped = [];
        foreach ($allRows as $row) {
            $tn = $get('ticket_number', $row);
            if (!$tn) continue;
            $grouped[$tn][] = $row;
        }

        $imported = 0;
        $updated  = 0;
        $errors   = [];

        foreach ($grouped as $ticketNumber => $rows) {
            $ticket = \Illuminate\Support\Facades\DB::table('ticket')
                ->where('ticket_number', $ticketNumber)
                ->whereNull('deleted_at')
                ->select('ticket_id')
                ->first();

            if (!$ticket) {
                $errors[] = "Ticket #{$ticketNumber}: tidak ditemukan — semua baris tiket ini dilewati";
                continue;
            }

            $detailRows = [];
            $totalMd    = 0;

            foreach ($rows as $row) {
                $eci = $get('employee_eci', $row);
                // Normalisasi nilai error Excel (#N/A, #REF!, dll)
                if ($eci && preg_match('/^#[A-Z\/]+[!?]?$/', $eci)) $eci = null;
                if (!$eci) { $errors[] = "Ticket #{$ticketNumber}: kolom Employee ECI kosong di satu baris — baris dilewati"; continue; }

                $employee = \Illuminate\Support\Facades\DB::table('employee')->where('eci', $eci)->select('employee_id')->first();
                if (!$employee) { $errors[] = "Ticket #{$ticketNumber}: ECI '{$eci}' tidak ditemukan — baris dilewati"; continue; }

                $md     = (float) ($get('mandays', $row) ?? 0);
                $addMd  = (float) ($get('additional_mandays', $row) ?? 0);
                $module = $get('module', $row);
                $notes  = $get('notes', $row);

                // Deduplikasi: kalau employee ini sudah ada, ambil nilai terbesar
                $empId = $employee->employee_id;
                if (isset($detailRows[$empId])) {
                    $prev = $detailRows[$empId];
                    if (($md + $addMd) <= ($prev['mandays'] + $prev['additional_mandays'])) continue;
                    $totalMd -= $prev['mandays'] + $prev['additional_mandays'];
                }

                $totalMd += $md + $addMd;
                $detailRows[$empId] = [
                    'employee_id'        => $empId,
                    'module'             => $module,
                    'mandays'            => $md,
                    'additional_mandays' => $addMd,
                    'approved_additional'=> $addMd > 0 ? $addMd : 0,
                    'notes'              => $notes,
                ];
            }

            if (empty($detailRows)) continue;
            $detailRows = array_values($detailRows);

            try {
                \Illuminate\Support\Facades\DB::beginTransaction();

                $existing = \Illuminate\Support\Facades\DB::table('consultant_mandays')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->orderByDesc('created_at')
                    ->first();

                if ($existing) {
                    \Illuminate\Support\Facades\DB::table('consultant_mandays')->where('id', $existing->id)->update([
                        'status'              => 'approved',
                        'total_mandays'       => $totalMd,
                        'approved_by_head_id' => $adminEmpId,
                        'approved_at'         => now(),
                        'updated_at'          => now(),
                    ]);
                    \Illuminate\Support\Facades\DB::table('consultant_mandays_detail')
                        ->where('consultant_mandays_id', $existing->id)
                        ->delete();
                    $mandaysId = $existing->id;
                    $updated++;
                } else {
                    $mandaysId = \Illuminate\Support\Facades\DB::table('consultant_mandays')->insertGetId([
                        'ticket_id'            => $ticket->ticket_id,
                        'proposed_by_agent_id' => $adminEmpId,
                        'proposed_at'          => now(),
                        'last_edited_at'       => now(),
                        'status'               => 'approved',
                        'approved_by_head_id'  => $adminEmpId,
                        'approved_at'          => now(),
                        'total_mandays'        => $totalMd,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                    $imported++;
                }

                foreach ($detailRows as $detail) {
                    \Illuminate\Support\Facades\DB::table('consultant_mandays_detail')->insert(array_merge($detail, [
                        'consultant_mandays_id' => $mandaysId,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]));

                    // Auto-add employee as ticket member if not already
                    $existingMember = \Illuminate\Support\Facades\DB::table('ticket_member')
                        ->where('ticket_id', $ticket->ticket_id)
                        ->where('employee_id', $detail['employee_id'])
                        ->first();
                    if (!$existingMember) {
                        \Illuminate\Support\Facades\DB::table('ticket_member')->insert([
                            'ticket_id'   => $ticket->ticket_id,
                            'employee_id' => $detail['employee_id'],
                            'is_active'   => true,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    } elseif (!$existingMember->is_active) {
                        \Illuminate\Support\Facades\DB::table('ticket_member')
                            ->where('ticket_id', $ticket->ticket_id)
                            ->where('employee_id', $detail['employee_id'])
                            ->update(['is_active' => true, 'updated_at' => now()]);
                    }
                }

                \Illuminate\Support\Facades\DB::table('ticket')
                    ->where('ticket_id', $ticket->ticket_id)
                    ->update([
                        'resolution_days_status' => 'approved',
                        'man_days'               => $totalMd,
                        'updated_at'             => now(),
                    ]);

                \Illuminate\Support\Facades\DB::commit();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                $errors[] = "Ticket #{$ticketNumber}: " . $e->getMessage();
            }
        }

        Log::info('AdminBackupController: resolution days import', [
            'imported' => $imported, 'updated' => $updated, 'errors' => count($errors),
            'by'       => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Import selesai: {$imported} ditambahkan, {$updated} diperbarui" . (count($errors) ? ', ' . count($errors) . ' error' : ''),
            'imported' => $imported,
            'updated'  => $updated,
            'errors'   => $errors,
        ]);
    }

    // ── Template Timesheet ────────────────────────────────────────────────────────

    public function templateTimesheet()
    {
        if (!$this->assertAdmin()) abort(403);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Timesheet');
        $sheet->fromArray([
            [
                'Employee ECI', 'Date', 'Start Time', 'End Time',
                'Description', 'Ticket Number', 'Activity Type',
                'Status', 'Is Billable', 'Presence', 'Location',
                'MD Consumed', 'Period Year', 'Period Month', 'Notes',
            ],
            [
                'ECI001', '2026-06-14', '08:00', '17:00',
                'Troubleshooting login issue', '100000001', 'Support',
                'submitted', 'Yes', 'WFO', 'Jakarta',
                '1.0', '2026', '6', 'Follow-up meeting scheduled',
            ],
            [
                'ECI002', '2026-06-14', '09:00', '12:00',
                'UAT session with customer', '100000002', 'Support',
                'submitted', 'Yes', 'WFH', '',
                '0.5', '2026', '6', '',
            ],
        ]);
        $sheet->getStyle('A1:O1')->getFont()->setBold(true);

        // Add note sheet for valid values
        $noteSheet = $spreadsheet->createSheet();
        $noteSheet->setTitle('Panduan');
        $noteSheet->fromArray([
            ['Kolom', 'Nilai Valid', 'Keterangan'],
            ['Status', 'draft / submitted / approved', 'Default: submitted'],
            ['Is Billable', 'Yes / No', 'Yes = jam kerja bisa ditagih ke customer; No = internal (rapat, training, admin)'],
            ['Presence', 'WFO / WFH / Remote / Hybrid', 'Work From Office / Home / Remote / Hybrid'],
            ['Activity Type', 'support / development / meeting / documentation / testing / training / other', 'Nilai harus huruf kecil (lowercase)'],
            ['Ticket Number', '(opsional)', 'Nomor tiket jika timesheet terkait tiket'],
            ['Date', 'YYYY-MM-DD', 'Contoh: 2026-06-14'],
            ['Start Time', 'HH:mm', 'Contoh: 08:00'],
            ['End Time', 'HH:mm', 'Contoh: 17:00'],
            ['MD Consumed', 'Desimal', 'Mandays dikonsumsi, contoh: 1.0'],
            ['Period Year', 'Tahun', 'Opsional, default dari kolom Date'],
            ['Period Month', '1–12', 'Opsional, default dari kolom Date'],
        ]);
        $noteSheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (range('A', 'C') as $col) {
            $noteSheet->getColumnDimension($col)->setAutoSize(true);
        }

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        return response()->stream(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="timesheet_import_template.xlsx"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }

    // ── Import Timesheet ──────────────────────────────────────────────────────────

    public function importTimesheet(\Illuminate\Http\Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        set_time_limit(300);
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:20480']);

        $file    = $request->file('file');
        $ext     = strtolower($file->getClientOriginalExtension());
        $allRows = $this->readSpreadsheetFile($file->getRealPath(), $ext);

        if (!$allRows || count($allRows) < 2) {
            return response()->json(['success' => false, 'message' => 'File kosong atau tidak valid'], 422);
        }

        $rawHeaders = array_shift($allRows);
        $headerMap  = [];
        foreach ($rawHeaders as $i => $h) {
            $headerMap[strtolower(trim((string)$h))] = $i;
        }

        $aliasMap = [
            'employee_eci'   => ['employee eci', 'employee_eci', 'eci'],
            'date'           => ['date', 'tanggal'],
            'start_time'     => ['start time', 'start_time', 'jam mulai'],
            'end_time'       => ['end time', 'end_time', 'jam selesai'],
            'description'    => ['description', 'description ticket', 'deskripsi', 'keterangan'],
            'ticket_number'  => ['ticket number', 'ticket_number', 'tiket', 'no tiket'],
            'activity_type'  => ['activity type', 'activity_type', 'tipe aktivitas'],
            'status'         => ['status'],
            'is_billable'    => ['is billable', 'is_billable', 'billable'],
            'presence'       => ['presence', 'kehadiran'],
            'location'       => ['location', 'lokasi'],
            'md_consumed'    => ['md consumed', 'md_consumed', 'mandays consumed'],
            'period_year'    => ['period year', 'period_year', 'tahun periode'],
            'period_month'   => ['period month', 'period_month', 'bulan periode'],
            'notes'          => ['notes', 'catatan'],
        ];

        $colIdx = [];
        foreach ($aliasMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) { $colIdx[$field] = $headerMap[$alias]; break; }
            }
        }

        if (!isset($colIdx['employee_eci']) || !isset($colIdx['date'])) {
            return response()->json(['success' => false, 'message' => 'Kolom wajib tidak ditemukan: "Employee ECI" dan "Date"'], 422);
        }

        $get = function (string $field, array $row) use ($colIdx): ?string {
            if (!isset($colIdx[$field])) return null;
            $val = trim((string)($row[$colIdx[$field]] ?? ''));
            return $val !== '' ? $val : null;
        };

        $validStatuses  = ['draft', 'submitted', 'approved', 'rejected'];
        $validPresences = ['WFO', 'WFH', 'Remote', 'Hybrid'];

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        foreach ($allRows as $row) {
            $rowNum++;

            $eci = $get('employee_eci', $row);
            // Normalisasi nilai error Excel (#N/A, #REF!, dll)
            if ($eci && preg_match('/^#[A-Z\/]+[!?]?$/', $eci)) $eci = null;
            if (!$eci) { $errors[] = "Baris {$rowNum}: Employee ECI kosong — dilewati"; $skipped++; continue; }

            $employee = \Illuminate\Support\Facades\DB::table('employee')->where('eci', $eci)->select('employee_id')->first();
            if (!$employee) { $errors[] = "Baris {$rowNum}: ECI '{$eci}' tidak ditemukan — dilewati"; $skipped++; continue; }

            $date = $this->normalizeDate($get('date', $row));
            if (!$date) { $errors[] = "Baris {$rowNum}: Tanggal tidak valid — dilewati"; $skipped++; continue; }

            $startTime = $get('start_time', $row);
            $endTime   = $get('end_time',   $row);

            // Resolve ticket
            $ticketId = null;
            $tn = $get('ticket_number', $row);
            if ($tn) {
                $t = \Illuminate\Support\Facades\DB::table('ticket')
                    ->where('ticket_number', $tn)
                    ->whereNull('deleted_at')
                    ->select('ticket_id')
                    ->first();
                if (!$t) {
                    $errors[] = "[Peringatan] Baris {$rowNum}: Ticket #{$tn} tidak ditemukan — timesheet tetap dibuat tanpa ticket";
                } else {
                    $ticketId = $t->ticket_id;
                }
            }

            // Duplicate check: same employee + date + start_time + ticket
            $dupQuery = \Illuminate\Support\Facades\DB::table('timesheets')
                ->where('employee_id', $employee->employee_id)
                ->where('date', $date)
                ->whereNull('deleted_at');
            if ($startTime) $dupQuery->where('start_time', $startTime);
            if ($ticketId)  $dupQuery->where('ticket_id', $ticketId);

            if ($dupQuery->exists()) {
                $errors[] = "[Peringatan] Baris {$rowNum}: Timesheet duplikat (ECI={$eci}, date={$date}" . ($startTime ? ", start={$startTime}" : '') . ") — dilewati";
                $skipped++;
                continue;
            }

            // Normalize status
            $statusRaw = strtolower($get('status', $row) ?? '');
            $status    = in_array($statusRaw, $validStatuses, true) ? $statusRaw : 'submitted';

            // Normalize is_billable
            $billableRaw = strtolower($get('is_billable', $row) ?? 'yes');
            $isBillable  = !in_array($billableRaw, ['no', 'false', '0'], true);

            // Normalize presence
            $presenceRaw = $get('presence', $row);
            $presence    = null;
            if ($presenceRaw) {
                foreach ($validPresences as $vp) {
                    if (strcasecmp($presenceRaw, $vp) === 0) { $presence = $vp; break; }
                }
                if (!$presence) $presence = $presenceRaw;
            }

            // Period defaults
            $periodYear  = (int) ($get('period_year',  $row) ?? date('Y', strtotime($date)));
            $periodMonth = (int) ($get('period_month', $row) ?? date('n', strtotime($date)));

            try {
                \Illuminate\Support\Facades\DB::table('timesheets')->insert([
                    'employee_id'          => $employee->employee_id,
                    'ticket_id'            => $ticketId,
                    'delivery_projects_id' => null,
                    'activity_id'          => null,
                    'date'                 => $date,
                    'start_time'           => $startTime,
                    'end_time'             => $endTime,
                    'description'          => $get('description', $row),
                    'activity_type'        => $get('activity_type', $row),
                    'status'               => $status,
                    'is_billable'          => $isBillable,
                    'presence'             => $presence,
                    'location'             => $get('location', $row),
                    'md_consumed'          => $get('md_consumed', $row) !== null ? (float) $get('md_consumed', $row) : null,
                    'notes'                => $get('notes', $row),
                    'period_year'          => $periodYear,
                    'period_month'         => $periodMonth,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $imported++;

                // Auto-add employee as ticket member if not already
                if ($ticketId) {
                    $existingMember = \Illuminate\Support\Facades\DB::table('ticket_member')
                        ->where('ticket_id', $ticketId)
                        ->where('employee_id', $employee->employee_id)
                        ->first();
                    if (!$existingMember) {
                        \Illuminate\Support\Facades\DB::table('ticket_member')->insert([
                            'ticket_id'   => $ticketId,
                            'employee_id' => $employee->employee_id,
                            'is_active'   => true,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    } elseif (!$existingMember->is_active) {
                        \Illuminate\Support\Facades\DB::table('ticket_member')
                            ->where('ticket_id', $ticketId)
                            ->where('employee_id', $employee->employee_id)
                            ->update(['is_active' => true, 'updated_at' => now()]);
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
                $skipped++;
            }
        }

        Log::info('AdminBackupController: timesheet import', [
            'imported' => $imported, 'skipped' => $skipped, 'errors' => count($errors),
            'by'       => session('user.eci') ?? session('user.name') ?? 'admin',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Import selesai: {$imported} ditambahkan" . ($skipped ? ", {$skipped} dilewati" : '') . (count($errors) ? ', ' . count($errors) . ' peringatan' : ''),
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }

    // ── Import Timesheet (Head & above) ───────────────────────────────────────────
    // Endpoint terpisah dari admin backup agar bisa diakses role head/RPMO/admin.

    public function importTimesheetHead(\Illuminate\Http\Request $request)
    {
        if (!$this->assertHeadOrAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        set_time_limit(300);
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:20480']);

        $file    = $request->file('file');
        $ext     = strtolower($file->getClientOriginalExtension());
        $allRows = $this->readSpreadsheetFile($file->getRealPath(), $ext);

        if (!$allRows || count($allRows) < 2) {
            return response()->json(['success' => false, 'message' => 'File kosong atau tidak valid'], 422);
        }

        $rawHeaders = array_shift($allRows);
        $headerMap  = [];
        foreach ($rawHeaders as $i => $h) {
            $headerMap[strtolower(trim((string)$h))] = $i;
        }

        $aliasMap = [
            'employee_eci'   => ['employee eci', 'employee_eci', 'eci'],
            'date'           => ['date', 'tanggal'],
            'start_time'     => ['start time', 'start_time', 'jam mulai'],
            'end_time'       => ['end time', 'end_time', 'jam selesai'],
            'description'    => ['description', 'description ticket', 'deskripsi', 'keterangan'],
            'ticket_number'  => ['ticket number', 'ticket_number', 'tiket', 'no tiket'],
            'activity_type'  => ['activity type', 'activity_type', 'tipe aktivitas'],
            'status'         => ['status'],
            'is_billable'    => ['is billable', 'is_billable', 'billable'],
            'presence'       => ['presence', 'kehadiran'],
            'location'       => ['location', 'lokasi'],
            'md_consumed'    => ['md consumed', 'md_consumed', 'mandays consumed'],
            'period_year'    => ['period year', 'period_year', 'tahun periode'],
            'period_month'   => ['period month', 'period_month', 'bulan periode'],
            'notes'          => ['notes', 'catatan'],
        ];

        $colIdx = [];
        foreach ($aliasMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) { $colIdx[$field] = $headerMap[$alias]; break; }
            }
        }

        if (!isset($colIdx['employee_eci']) || !isset($colIdx['date'])) {
            return response()->json(['success' => false, 'message' => 'Kolom wajib tidak ditemukan: "Employee ECI" dan "Date"'], 422);
        }

        $get = function (string $field, array $row) use ($colIdx): ?string {
            if (!isset($colIdx[$field])) return null;
            $val = trim((string)($row[$colIdx[$field]] ?? ''));
            return $val !== '' ? $val : null;
        };

        $validStatuses  = ['draft', 'submitted', 'approved', 'rejected'];
        $validPresences = ['WFO', 'WFH', 'Remote', 'Hybrid'];

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        foreach ($allRows as $row) {
            $rowNum++;

            $eci = $get('employee_eci', $row);
            if ($eci && preg_match('/^#[A-Z\/]+[!?]?$/', $eci)) $eci = null;
            if (!$eci) { $errors[] = "Baris {$rowNum}: Employee ECI kosong — dilewati"; $skipped++; continue; }

            $employee = \Illuminate\Support\Facades\DB::table('employee')->where('eci', $eci)->select('employee_id')->first();
            if (!$employee) { $errors[] = "Baris {$rowNum}: ECI '{$eci}' tidak ditemukan — dilewati"; $skipped++; continue; }

            $date = $this->normalizeDate($get('date', $row));
            if (!$date) { $errors[] = "Baris {$rowNum}: Tanggal tidak valid — dilewati"; $skipped++; continue; }

            $startTime = $get('start_time', $row);
            $endTime   = $get('end_time',   $row);

            $ticketId = null;
            $tn = $get('ticket_number', $row);
            if ($tn) {
                $t = \Illuminate\Support\Facades\DB::table('ticket')
                    ->where('ticket_number', $tn)
                    ->whereNull('deleted_at')
                    ->select('ticket_id')
                    ->first();
                if (!$t) {
                    $errors[] = "[Peringatan] Baris {$rowNum}: Ticket #{$tn} tidak ditemukan — timesheet tetap dibuat tanpa ticket";
                } else {
                    $ticketId = $t->ticket_id;
                }
            }

            $dupQuery = \Illuminate\Support\Facades\DB::table('timesheets')
                ->where('employee_id', $employee->employee_id)
                ->where('date', $date)
                ->whereNull('deleted_at');
            if ($startTime) $dupQuery->where('start_time', $startTime);
            if ($ticketId)  $dupQuery->where('ticket_id', $ticketId);

            if ($dupQuery->exists()) {
                $errors[] = "[Peringatan] Baris {$rowNum}: Timesheet duplikat (ECI={$eci}, date={$date}" . ($startTime ? ", start={$startTime}" : '') . ") — dilewati";
                $skipped++;
                continue;
            }

            $statusRaw = strtolower($get('status', $row) ?? '');
            $status    = in_array($statusRaw, $validStatuses, true) ? $statusRaw : 'submitted';

            $billableRaw = strtolower($get('is_billable', $row) ?? 'yes');
            $isBillable  = !in_array($billableRaw, ['no', 'false', '0'], true);

            $presenceRaw = $get('presence', $row);
            $presence    = null;
            if ($presenceRaw) {
                foreach ($validPresences as $vp) {
                    if (strcasecmp($presenceRaw, $vp) === 0) { $presence = $vp; break; }
                }
                if (!$presence) $presence = $presenceRaw;
            }

            $periodYear  = (int) ($get('period_year',  $row) ?? date('Y', strtotime($date)));
            $periodMonth = (int) ($get('period_month', $row) ?? date('n', strtotime($date)));

            try {
                \Illuminate\Support\Facades\DB::table('timesheets')->insert([
                    'employee_id'          => $employee->employee_id,
                    'ticket_id'            => $ticketId,
                    'delivery_projects_id' => null,
                    'activity_id'          => null,
                    'date'                 => $date,
                    'start_time'           => $startTime,
                    'end_time'             => $endTime,
                    'description'          => $get('description', $row),
                    'activity_type'        => $get('activity_type', $row),
                    'status'               => $status,
                    'is_billable'          => $isBillable,
                    'presence'             => $presence,
                    'location'             => $get('location', $row),
                    'md_consumed'          => $get('md_consumed', $row) !== null ? (float) $get('md_consumed', $row) : null,
                    'notes'                => $get('notes', $row),
                    'period_year'          => $periodYear,
                    'period_month'         => $periodMonth,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $imported++;

                if ($ticketId) {
                    $existingMember = \Illuminate\Support\Facades\DB::table('ticket_member')
                        ->where('ticket_id', $ticketId)
                        ->where('employee_id', $employee->employee_id)
                        ->first();
                    if (!$existingMember) {
                        \Illuminate\Support\Facades\DB::table('ticket_member')->insert([
                            'ticket_id'   => $ticketId,
                            'employee_id' => $employee->employee_id,
                            'is_active'   => true,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    } elseif (!$existingMember->is_active) {
                        \Illuminate\Support\Facades\DB::table('ticket_member')
                            ->where('ticket_id', $ticketId)
                            ->where('employee_id', $employee->employee_id)
                            ->update(['is_active' => true, 'updated_at' => now()]);
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
                $skipped++;
            }
        }

        Log::info('AdminBackupController: timesheet import by head', [
            'imported' => $imported, 'skipped' => $skipped, 'errors' => count($errors),
            'by'       => session('user.eci') ?? session('user.name') ?? 'head',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => "Import selesai: {$imported} ditambahkan" . ($skipped ? ", {$skipped} dilewati" : '') . (count($errors) ? ', ' . count($errors) . ' peringatan' : ''),
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }

    // ── Spreadsheet Reader Helper ─────────────────────────────────────────────────

    private function readSpreadsheetFile(string $realPath, string $extension): ?array
    {
        if (in_array($extension, ['xlsx', 'xls', 'ods'])) {
            try {
                $spreadsheet = IOFactory::load($realPath);
                $sheet = $spreadsheet->getActiveSheet();
                $data  = $sheet->toArray(null, true, true, false);
                // Remove trailing completely-empty rows
                return array_values(array_filter(
                    $data,
                    fn($row) => !empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))
                ));
            } catch (\Exception $e) {
                return null;
            }
        }

        // CSV fallback
        $handle = fopen($realPath, 'r');
        if (!$handle) return null;

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && trim($row[0]) === '') continue;
            $rows[] = $row;
        }
        fclose($handle);

        return $rows ?: null;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}

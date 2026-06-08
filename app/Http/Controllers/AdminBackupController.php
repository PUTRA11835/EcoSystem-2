<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminBackupController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
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
            ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
            ->leftJoin('employee_identification as ei', function ($join) {
                $join->on('ei.employee_id', '=', 'e.employee_id')
                     ->where('ei.identification_type', 'KTP');
            })
            ->select(
                'e.eci', 'r.name as role_name', 'e.is_active',
                'b.title', 'b.nick_name', 'b.gender', 'b.religion',
                'b.first_name', 'b.last_name',
                'b.marital_status', 'b.birth_date', 'b.birth_place',
                'b.personnel_area', 'b.personnel_subarea',
                'b.employee_group', 'b.employee_subgroup',
                'b.position', 'b.division', 'b.department',
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

        $rows = DB::table('customer as c')
            ->leftJoin('customer_basic_data as b', 'c.customer_id', '=', 'b.customer_id')
            ->leftJoin('customer as p', 'c.parent_customer_id', '=', 'p.customer_id')
            ->select(
                'c.customer_id', 'c.customer_code', 'c.email', 'c.is_active',
                'b.title', 'b.name_1', 'b.name_2',
                'b.customer_group', 'b.customer_category', 'b.industry_sector',
                'b.ec_account_executive', 'b.sap_account_executive',
                'p.customer_code as parent_customer_code',
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

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="employees_import_template.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            // Header columns — snake_case, compatible with both full names and Excel-truncated variants
            fputcsv($handle, [
                'ECI', 'role', 'status', 'email',
                'title', 'nick_name', 'gender', 'religion',
                'first_name', 'last_name',
                'marital_status', 'birth_date', 'birth_place',
                'personnel_area', 'personnel_subarea',
                'employee_group', 'employee_subgroup',
                'position', 'division', 'department',
                'since_date', 'nik',
            ]);
            // Example row
            fputcsv($handle, [
                'ECI001', 'Delivery Support User', 'Active', 'john.doe@example.com',
                'Mr.', 'John', 'Male', 'Islam',
                'John', 'Doe',
                'Single', '1990-01-15', 'Jakarta',
                'Area A', 'Sub Area A',
                'Group 1', 'Subgroup 1',
                'Consultant', 'IT', 'Support',
                '2023-01-01', '3201010101900001',
            ]);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function templateCustomers()
    {
        if (!$this->assertAdmin()) abort(403);

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="customers_import_template.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Customer Code', 'Email', 'Status',
                'Title', 'Company Name', 'Name 2',
                'Customer Group', 'Customer Category', 'Industry Sector',
                'EC Account Executive', 'SAP Account Executive',
                'Parent Customer Code',
            ]);
            fputcsv($handle, [
                'CUST001', 'company@example.com', 'Active',
                'PT', 'Example Company Tbk', '',
                'Corporate', '', 'Technology',
                'John Smith', '',
                '',
            ]);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
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

        $rawHeaders = fgetcsv($handle);
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
            'since_date'        => ['since_date', 'Since Date'],
            'nik'               => ['nik', 'NIK', 'nik (identification_type)', 'NIK (identification_type)'],
            'email'             => ['email', 'Email', 'email_work', 'Email Work'],
        ];

        // Build colIndex: canonical_field => column index in CSV
        $colIndex = [];
        foreach ($aliasMap as $field => $aliases) {
            foreach ($rawHeaders as $i => $header) {
                $h = strtolower(trim($header));
                foreach ($aliases as $alias) {
                    if (strtolower(trim($alias)) === $h) {
                        $colIndex[$field] = $i;
                        break 2;
                    }
                }
            }
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
        $imported = 0;
        $updated  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue;

            $eci = $get('eci');
            if (!$eci) { $errors[] = "Baris {$rowNum}: ECI wajib diisi"; continue; }

            $roleName = $get('role');
            $roleId   = $roleName ? ($roles[$roleName] ?? null) : null;
            if ($roleName && !$roleId) {
                $errors[] = "Baris {$rowNum}: Role '{$roleName}' tidak ditemukan";
                continue;
            }

            $isActive = $get('status') !== null
                ? (strtolower($get('status')) === 'active' ? 1 : 0)
                : 1;

            $basicData = array_filter([
                'title'              => $get('title'),
                'first_name'         => $get('first_name'),
                'last_name'          => $get('last_name'),
                'nick_name'          => $get('nick_name'),
                'gender'             => $get('gender'),
                'religion'           => $get('religion'),
                'birth_date'         => $get('birth_date'),
                'birth_place'        => $get('birth_place'),
                'marital_status'     => $get('marital_status'),
                'personnel_area'     => $get('personnel_area'),
                'personnel_subarea'  => $get('personnel_subarea'),
                'employee_group'     => $get('employee_group'),
                'employee_subgroup'  => $get('employee_subgroup'),
                'position'           => $get('position'),
                'division'           => $get('division'),
                'department'         => $get('department'),
                'since_date'         => $get('since_date'),
            ], fn($v) => $v !== null);

            $nik = $get('nik');

            try {
                $existing = DB::table('employee')->where('eci', $eci)->first();

                if ($existing) {
                    $empUpdate = ['is_active' => $isActive, 'updated_at' => now()];
                    if ($roleId) $empUpdate['role_id'] = $roleId;
                    DB::table('employee')->where('eci', $eci)->update($empUpdate);

                    // Update email di auth_users jika CSV mengisi email dan auth_users belum punya email
                    $email = $get('email');
                    if ($email) {
                        DB::table('auth_users')
                            ->where('employee_id', $existing->employee_id)
                            ->whereNull('email')
                            ->update(['email' => $email, 'updated_at' => now()]);
                    }

                    if ($basicData) {
                        $basicData['updated_at'] = now();
                        if (DB::table('employee_basic_data')->where('employee_id', $existing->employee_id)->exists()) {
                            DB::table('employee_basic_data')->where('employee_id', $existing->employee_id)->update($basicData);
                        } else {
                            $basicData['employee_id'] = $existing->employee_id;
                            $basicData['created_at']  = now();
                            DB::table('employee_basic_data')->insert($basicData);
                        }
                    }

                    if ($nik) {
                        $this->upsertNik($existing->employee_id, $nik);
                    }

                    $updated++;
                } else {
                    if (!$roleId) {
                        $errors[] = "Baris {$rowNum}: ECI '{$eci}' baru tapi Role tidak valid — baris dilewati";
                        continue;
                    }

                    $email = $get('email');
                    if (!$email) {
                        $errors[] = "Baris {$rowNum} ({$eci}): Kolom 'email' wajib diisi untuk employee baru";
                        continue;
                    }

                    $employeeId = DB::table('employee')->insertGetId([
                        'role_id'    => $roleId,
                        'eci'        => $eci,
                        'is_active'  => $isActive,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Role assignment
                    DB::table('employee_role_assignment')->insertOrIgnore([
                        'employee_id' => $employeeId,
                        'role_id'     => $roleId,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);

                    // Auth account — password default = ECI, sistem kirim email setup password saat login pertama
                    if (!DB::table('auth_users')->where('employee_id', $employeeId)->exists()) {
                        DB::table('auth_users')->insert([
                            'employee_id'   => $employeeId,
                            'customer_id'   => null,
                            'username'      => $eci,
                            'email'         => $email,
                            'phone'         => null,
                            'password'      => \Illuminate\Support\Facades\Hash::make($eci, ['rounds' => 8]),
                            'is_active'     => $isActive,
                            'is_already_cp' => false,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
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

                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum} ({$eci}): " . $e->getMessage();
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

        $get = function (string $col, array $row) use ($headerMap): ?string {
            if (!isset($headerMap[$col])) return null;
            $val = trim($row[$headerMap[$col]] ?? '');
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

            $basicData = array_filter([
                'name_1'                => $companyName,
                'name_2'               => $get('Name 2', $row),
                'title'                => $get('Title', $row),
                'customer_group'       => $get('Customer Group', $row),
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
                        'customer_code' => $customerCode,
                        'email'         => $email,
                        'is_active'     => $isActive,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);

                    $basicData['customer_id'] = $customerId;
                    $basicData['created_at']  = now();
                    $basicData['updated_at']  = now();
                    DB::table('customer_basic_data')->insert($basicData);
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

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminBackupController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === 1;
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

            $passArg = $pass !== '' ? '-p' . escapeshellarg($pass) : '';
            // Build command — redirect stderr to a temp file to capture errors
            $errFile = $filepath . '.err';
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = sprintf(
                    '"%s" -h %s -P %s -u %s %s %s > "%s" 2>"%s"',
                    $mysqldump,
                    escapeshellarg($host),
                    $port,
                    escapeshellarg($user),
                    $pass !== '' ? '-p' . $pass : '',
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
                    $pass !== '' ? '-p' . escapeshellarg($pass) : '',
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
            ->select(
                'e.employee_id', 'e.eci', 'e.is_active',
                'r.name as role_name',
                'b.title', 'b.first_name', 'b.last_name', 'b.nick_name', 'b.gender',
                'b.birth_date', 'b.birth_place', 'b.marital_status', 'b.religion',
                'b.position', 'b.division', 'b.department',
                'b.personnel_area', 'b.employee_group', 'b.employee_subgroup',
                'b.since_date',
                'e.created_at'
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
            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'Employee ID', 'ECI', 'Status', 'Role',
                'Title', 'First Name', 'Last Name', 'Nick Name', 'Gender',
                'Birth Date', 'Birth Place', 'Marital Status', 'Religion',
                'Position', 'Division', 'Department',
                'Personnel Area', 'Employee Group', 'Employee Subgroup',
                'Since Date', 'Created At',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->employee_id, $r->eci, $r->is_active ? 'Active' : 'Inactive', $r->role_name,
                    $r->title, $r->first_name, $r->last_name, $r->nick_name, $r->gender,
                    $r->birth_date, $r->birth_place, $r->marital_status, $r->religion,
                    $r->position, $r->division, $r->department,
                    $r->personnel_area, $r->employee_group, $r->employee_subgroup,
                    $r->since_date, $r->created_at,
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
            ->leftJoin('employee as e',              't.employee_id',   '=', 'e.employee_id')
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
            ->select(
                'c.customer_id', 'c.customer_code', 'c.email', 'c.is_active',
                'b.title', 'b.name_1', 'b.name_2',
                'b.customer_group', 'b.customer_category', 'b.industry_sector',
                'b.ec_account_executive', 'b.sap_account_executive',
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
                'Created At',
            ]);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r->customer_id, $r->customer_code ?? '', $r->email ?? '',
                    $r->is_active ? 'Active' : 'Inactive',
                    $r->title ?? '', $r->name_1 ?? '', $r->name_2 ?? '',
                    $r->customer_group ?? '', $r->customer_category ?? '', $r->industry_sector ?? '',
                    $r->ec_account_executive ?? '', $r->sap_account_executive ?? '',
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
            fputcsv($handle, [
                'ECI', 'Status', 'Role',
                'Title', 'First Name', 'Last Name', 'Nick Name', 'Gender',
                'Birth Date', 'Birth Place', 'Marital Status', 'Religion',
                'Position', 'Division', 'Department',
                'Personnel Area', 'Employee Group', 'Employee Subgroup', 'Since Date',
            ]);
            fputcsv($handle, [
                'ECI001', 'Active', 'Employee',
                'Mr.', 'John', 'Doe', 'John', 'Male',
                '1990-01-15', 'Jakarta', 'Single', 'Islam',
                'Consultant', 'IT', 'Support',
                'Area A', 'Group 1', 'Subgroup 1', '2023-01-01',
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
            ]);
            fputcsv($handle, [
                'CUST001', 'company@example.com', 'Active',
                'PT', 'Example Company Tbk', '',
                'Corporate', '', 'Technology',
                'John Smith', '',
            ]);
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Import Employee ───────────────────────────────────────────────────────

    public function importEmployees(Request $request)
    {
        if (!$this->assertAdmin()) return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'File CSV kosong atau tidak valid'], 422);
        }

        $headerMap = array_flip(array_map('trim', $rawHeaders));

        if (!isset($headerMap['ECI'])) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Kolom "ECI" wajib ada di CSV'], 422);
        }

        $roles    = DB::table('employee_role')->pluck('id', 'name');
        $imported = 0;
        $updated  = 0;
        $errors   = [];
        $rowNum   = 1;

        $get = function (string $col, array $row) use ($headerMap): ?string {
            if (!isset($headerMap[$col])) return null;
            $val = trim($row[$headerMap[$col]] ?? '');
            return $val !== '' ? $val : null;
        };

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim($row[0]) === '') continue;

            $eci = $get('ECI', $row);
            if (!$eci) { $errors[] = "Baris {$rowNum}: ECI wajib diisi"; continue; }

            $roleName = $get('Role', $row);
            $roleId   = $roleName ? ($roles[$roleName] ?? null) : null;
            if ($roleName && !$roleId) {
                $errors[] = "Baris {$rowNum}: Role '{$roleName}' tidak ditemukan — baris dilewati";
                continue;
            }

            $isActive = $get('Status', $row) !== null
                ? (strtolower($get('Status', $row)) === 'active' ? 1 : 0)
                : 1;

            $basicData = array_filter([
                'title'             => $get('Title', $row),
                'first_name'        => $get('First Name', $row),
                'last_name'         => $get('Last Name', $row),
                'nick_name'         => $get('Nick Name', $row),
                'gender'            => $get('Gender', $row),
                'birth_date'        => $get('Birth Date', $row),
                'birth_place'       => $get('Birth Place', $row),
                'marital_status'    => $get('Marital Status', $row),
                'religion'          => $get('Religion', $row),
                'position'          => $get('Position', $row),
                'division'          => $get('Division', $row),
                'department'        => $get('Department', $row),
                'personnel_area'    => $get('Personnel Area', $row),
                'employee_group'    => $get('Employee Group', $row),
                'employee_subgroup' => $get('Employee Subgroup', $row),
                'since_date'        => $get('Since Date', $row),
            ], fn($v) => $v !== null);

            try {
                $existing = DB::table('employee')->where('eci', $eci)->first();

                if ($existing) {
                    $empUpdate = ['is_active' => $isActive, 'updated_at' => now()];
                    if ($roleId) $empUpdate['role_id'] = $roleId;
                    DB::table('employee')->where('eci', $eci)->update($empUpdate);

                    if ($basicData) {
                        $basicData['updated_at'] = now();
                        $exists = DB::table('employee_basic_data')->where('employee_id', $existing->employee_id)->exists();
                        if ($exists) {
                            DB::table('employee_basic_data')->where('employee_id', $existing->employee_id)->update($basicData);
                        } else {
                            $basicData['employee_id'] = $existing->employee_id;
                            $basicData['created_at']  = now();
                            DB::table('employee_basic_data')->insert($basicData);
                        }
                    }
                    $updated++;
                } else {
                    if (!$roleId) {
                        $errors[] = "Baris {$rowNum}: ECI '{$eci}' baru tapi Role tidak valid — baris dilewati";
                        continue;
                    }
                    $employeeId = DB::table('employee')->insertGetId([
                        'role_id'    => $roleId,
                        'eci'        => $eci,
                        'is_active'  => $isActive,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $basicData['employee_id'] = $employeeId;
                    $basicData['created_at']  = now();
                    $basicData['updated_at']  = now();
                    DB::table('employee_basic_data')->insert($basicData);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
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
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        fclose($handle);

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

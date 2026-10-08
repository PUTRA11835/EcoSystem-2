<?php

namespace Database\Seeders;

use App\Models\Attendance\AttendanceRecord as R;
use App\Services\Attendance\GeofenceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh Daily Recap untuk UJI dan LAPORAN (bukan data sungguhan).
 *
 * Mengambil beberapa karyawan role "Delivery Support User" yang hanya punya role dasar (bukan atasan/kepala/
 * admin), lalu membuat satu catatan presensi per orang untuk satu tanggal dengan skenario berikut:
 *   1. GPS tercetak lengkap, di dalam radius kantor, check-out ada      → baris "normal"
 *   2. Akurasi GPS rendah (241 m), di dalam radius                       → catatan "Low GPS accuracy"
 *   3. GPS tidak tersedia (izin lokasi ditolak) di kedua sisi            → "Location access was blocked…"
 *   4. Koordinat ada, tetapi tak ada lokasi kantor terdaftar untuk dibandingkan → "Location received, but not checked…"
 *   5. Incomplete #1: Late, check-in tanpa check-out                     → "No check-out yet"
 *   6. Incomplete #2: Present, check-in tanpa check-out                  → "No check-out yet"
 *
 * Aman diulang: karyawan yang sudah punya catatan pada tanggal itu dilewati, dan setiap baris buatan seeder
 * ditandai `check_in_device = 'Seeder demo'` sehingga pembersihannya tepat sasaran (tidak menyentuh data lain).
 *
 *   php artisan db:seed --class=AttendanceDemoSeeder                  # tanggal = hari kerja terakhir sebelum hari ini
 *   ATT_DEMO_DATE=2026-10-06 php artisan db:seed --class=AttendanceDemoSeeder
 *   ATT_DEMO_CLEAN=1 php artisan db:seed --class=AttendanceDemoSeeder  # hapus HANYA baris bertanda seeder
 *
 * Tidak didaftarkan di DatabaseSeeder dan menolak berjalan di production.
 */
class AttendanceDemoSeeder extends Seeder
{
    private const MARK = 'Seeder demo';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->warn('Dihentikan: seeder data contoh tidak boleh dijalankan di production.');

            return;
        }

        if (env('ATT_DEMO_CLEAN')) {
            $n = DB::table('attendance_records')->where('check_in_device', self::MARK)->delete();
            $this->info("Dibersihkan: {$n} catatan presensi contoh.");

            return;
        }

        $date = $this->resolveDate();
        $geo  = app(GeofenceService::class);

        $branch = DB::table('branches')->where('is_active', 1)->orderBy('id')->first();
        if (!$branch) {
            $this->warn('Tidak ada cabang aktif — skenario di dalam radius memerlukan satu lokasi kantor. Dihentikan.');

            return;
        }
        $candidates = [['type' => 'office', 'id' => (int) $branch->id, 'latitude' => $branch->latitude, 'longitude' => $branch->longitude, 'radius_meters' => (int) $branch->radius_meters]];

        $employees = $this->pickEmployees($date, 6);
        if (count($employees) < 6) {
            $this->warn('Karyawan Delivery Support yang memenuhi syarat kurang dari 6 — dihentikan.');

            return;
        }

        // Titik ±10 m dari kantor (deterministik).
        $near = ['latitude' => (float) $branch->latitude - 0.00004, 'longitude' => (float) $branch->longitude + 0.00008];

        $scenarios = [
            ['normal',       '07:58', '17:06', $near + ['accuracy' => 12.0, 'gps_status' => 'gps_ok'], $near + ['accuracy' => 15.0, 'gps_status' => 'gps_ok'], 0],
            ['low_accuracy', '08:03', '17:02', $near + ['accuracy' => 241.0, 'gps_status' => 'gps_ok'], $near + ['accuracy' => 88.0, 'gps_status' => 'gps_ok'], 3],
            ['no_gps',       '08:00', '17:00', ['gps_status' => 'gps_permission_denied'], ['gps_status' => 'gps_permission_denied'], 0],
            ['no_location',  '08:01', '17:01', $near + ['accuracy' => 20.0, 'gps_status' => 'gps_ok', 'skip_geofence' => true], $near + ['accuracy' => 20.0, 'gps_status' => 'gps_ok', 'skip_geofence' => true], 1],
            ['incomplete_late',    '08:42', null, $near + ['accuracy' => 30.0, 'gps_status' => 'gps_ok'], null, 42],
            ['incomplete_present', '07:55', null, $near + ['accuracy' => 25.0, 'gps_status' => 'gps_ok'], null, 0],
        ];

        $now = now();
        foreach ($scenarios as $i => [$name, $in, $out, $inPoint, $outPoint, $late]) {
            $emp = $employees[$i];
            $inAt  = Carbon::parse("{$date} {$in}:00");
            $outAt = $out ? Carbon::parse("{$date} {$out}:00") : null;

            $row = [
                'employee_id' => $emp->employee_id, 'attendance_date' => $date, 'shift_id' => 1,
                'late_minutes' => $late, 'early_leave_minutes' => 0,
                'day_status' => $late > 0 ? R::STATUS_LATE : R::STATUS_PRESENT,
                'source' => R::SOURCE_ESS, 'period_year' => (int) substr($date, 0, 4), 'period_month' => (int) substr($date, 5, 2),
                'notes' => "Data contoh ({$name})", 'created_at' => $now, 'updated_at' => $now,
            ];
            $flags = [];

            [$cols, $f] = $this->side('check_in', $inAt, $inPoint, $geo, $candidates);
            $row += $cols;
            $flags = array_merge($flags, $f);

            if ($outAt) {
                [$cols, $f] = $this->side('check_out', $outAt, $outPoint, $geo, $candidates);
                $row += $cols;
                $flags = array_merge($flags, $f);
                $gross = (int) floor($inAt->diffInMinutes($outAt));
                $row['work_minutes'] = max(0, $gross - 60);
            } else {
                $row['work_minutes'] = 0;
            }
            $row['flags'] = $flags ? json_encode(array_values(array_unique($flags))) : null;

            DB::table('attendance_records')->insert($row);
            $this->info(sprintf('  %-18s → %s (%s)', $name, $emp->eci, trim($emp->first_name . ' ' . $emp->last_name)));
        }

        $this->info("Selesai: 6 catatan contoh untuk {$date}. Lihat di Attendance › Daily Recap. Hapus dengan ATT_DEMO_CLEAN=1.");
    }

    /** Kolom satu sisi punch + flag bersisinya, memakai logika GeofenceService yang sama dengan aplikasi. */
    private function side(string $side, Carbon $at, array $p, GeofenceService $geo, array $candidates): array
    {
        $hasCoords = isset($p['latitude'], $p['longitude']);
        $skip = !empty($p['skip_geofence']);

        $res = $geo->evaluate(
            $hasCoords ? (float) $p['latitude'] : null,
            $hasCoords ? (float) $p['longitude'] : null,
            isset($p['accuracy']) ? (float) $p['accuracy'] : null,
            $skip ? [] : $candidates,
            100
        );

        $flags = array_map(fn ($f) => R::sideFlag($side, $f), $res['flags']);

        return [[
            $side . '_at' => $at,
            $side . '_latitude' => $p['latitude'] ?? null,
            $side . '_longitude' => $p['longitude'] ?? null,
            $side . '_accuracy_m' => $p['accuracy'] ?? null,
            $side . '_connection' => $hasCoords ? '4g' : null,
            $side . '_gps_status' => $p['gps_status'],
            $side . '_match_type' => $res['match_type'],
            $side . '_branch_id' => $res['branch_id'],
            $side . '_project_site_id' => $res['project_site_id'],
            $side . '_distance_m' => $res['distance_m'],
            $side . '_ip' => '10.0.0.' . random_int(10, 99),
            $side . '_device' => $side === 'check_in' ? self::MARK : 'Seeder demo (out)',
            $side . '_source' => R::SOURCE_ESS,
        ], $flags];
    }

    /** Karyawan Delivery Support User "biasa": hanya role dasar, aktif, Internal, belum punya catatan hari itu. */
    private function pickEmployees(string $date, int $n): array
    {
        $dsRole = (int) DB::table('employee_role')->where('name', 'Delivery Support User')->value('id');
        $baseRoles = array_filter([
            $dsRole,
            (int) DB::table('employee_role')->where('name', 'EC User')->value('id'),
            (int) DB::table('employee_role')->where('name', 'User System Registered')->value('id'),
        ]);

        return DB::table('employee as e')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->where('e.is_active', 1)
            ->where('e.eci', 'not like', 'TEST%')->where('e.eci', 'not like', 'DEMO%')
            ->where('b.employee_type', 'Internal')
            ->whereIn('e.employee_id', DB::table('employee_role_assignment')->where('role_id', $dsRole)->select('employee_id'))
            ->whereNotIn('e.employee_id', DB::table('employee_role_assignment')->whereNotIn('role_id', $baseRoles)->select('employee_id'))
            // bukan atasan siapa pun
            ->whereNotIn('e.employee_id', DB::table('employee_basic_data')->whereNotNull('direct_supervision')->where('direct_supervision', '!=', '')->select(DB::raw('CAST(direct_supervision AS UNSIGNED)')))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('attendance_records as a')
                ->whereColumn('a.employee_id', 'e.employee_id')->where('a.attendance_date', $date))
            ->orderBy('e.employee_id')->limit($n)
            ->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name'])->all();
    }

    private function resolveDate(): string
    {
        if ($d = env('ATT_DEMO_DATE')) {
            return Carbon::parse($d)->toDateString();
        }
        $d = now()->subDay();
        while ($d->isWeekend()) {
            $d->subDay();
        }

        return $d->toDateString();
    }

    private function info(string $m): void
    {
        $this->command ? $this->command->info($m) : print($m . "\n");
    }

    private function warn(string $m): void
    {
        $this->command ? $this->command->warn($m) : print($m . "\n");
    }
}

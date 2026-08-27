<?php

namespace App\Models\Attendance;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan presensi harian. Satu baris = satu karyawan pada satu tanggal.
 */
class AttendanceRecord extends Model
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'employee_id', 'attendance_date',

        'check_in_at', 'check_in_latitude', 'check_in_longitude',
        'check_in_accuracy_m', 'check_in_connection', 'check_in_gps_status',
        'check_in_match_type', 'check_in_branch_id', 'check_in_project_site_id',
        'check_in_distance_m', 'check_in_ip', 'check_in_device', 'check_in_source',

        'check_out_at', 'check_out_latitude', 'check_out_longitude',
        'check_out_accuracy_m', 'check_out_connection', 'check_out_gps_status',
        'check_out_match_type', 'check_out_branch_id', 'check_out_project_site_id',
        'check_out_distance_m', 'check_out_ip', 'check_out_device', 'check_out_source',

        'shift_id', 'late_minutes', 'early_leave_minutes',
        'work_minutes', 'overtime_minutes',
        'day_status', 'source', 'flags', 'notes',
        'period_year', 'period_month',
    ];

    protected $casts = [
        'attendance_date'     => 'date',
        'check_in_at'         => 'datetime',
        'check_out_at'        => 'datetime',
        'check_in_latitude'   => 'decimal:8',
        'check_in_longitude'  => 'decimal:8',
        'check_out_latitude'  => 'decimal:8',
        'check_out_longitude' => 'decimal:8',
        'flags'               => 'array',
        'late_minutes'        => 'integer',
        'early_leave_minutes' => 'integer',
        'work_minutes'        => 'integer',
        'overtime_minutes'    => 'integer',
        'period_year'         => 'integer',
        'period_month'        => 'integer',
    ];

    // ── Konstanta: status GPS ───────────────────────────────────────────────
    // Kosakata sengaja sama persis dengan sistem referensi supaya hasil uji
    // terima dapat dibandingkan langsung tanpa penerjemahan.

    public const GPS_OK                = 'gps_ok';
    public const GPS_TIMEOUT           = 'gps_timeout';
    public const GPS_PERMISSION_DENIED = 'gps_permission_denied';
    public const GPS_UNSUPPORTED       = 'gps_unsupported';
    public const GPS_INSECURE_CONTEXT  = 'gps_insecure_context';
    public const GPS_UNAVAILABLE       = 'gps_unavailable';

    /**
     * Sistem operasi memblokir BROWSER-nya, bukan situs ini.
     *
     * Browser memakai kode galat yang sama (PERMISSION_DENIED) untuk "situs
     * diblokir pengguna" dan "browser diblokir OS". Keduanya dibedakan di sisi
     * klien: bila Permissions API menyatakan situs sudah 'granted' tetapi
     * permintaan tetap ditolak, penyebabnya OS. Perbedaan ini penting karena
     * langkah perbaikannya sama sekali berbeda.
     */
    public const GPS_SYSTEM_DENIED     = 'gps_system_denied';

    public const GPS_STATUSES = [
        self::GPS_OK,
        self::GPS_TIMEOUT,
        self::GPS_PERMISSION_DENIED,
        self::GPS_UNSUPPORTED,
        self::GPS_INSECURE_CONTEXT,
        self::GPS_UNAVAILABLE,
        self::GPS_SYSTEM_DENIED,
    ];

    // ── Konstanta: jenis lokasi + vonis geofence ────────────────────────────
    // Satu nilai memuat dua informasi: lokasi terdekat itu kantor atau lokasi
    // proyek, dan apakah presensinya di dalam radius. Digabung karena check-in
    // dan check-out masing-masing punya vonis sendiri, sehingga menyimpannya
    // di `flags` (yang tidak bersisi) akan ambigu.

    public const MATCH_OFFICE_IN   = 'office_in';
    public const MATCH_OFFICE_OUT  = 'office_out';
    public const MATCH_PROJECT_IN  = 'project_in';
    public const MATCH_PROJECT_OUT = 'project_out';
    public const MATCH_NONE        = 'none';

    public const MATCH_TYPES = [
        self::MATCH_OFFICE_IN,
        self::MATCH_OFFICE_OUT,
        self::MATCH_PROJECT_IN,
        self::MATCH_PROJECT_OUT,
        self::MATCH_NONE,
    ];

    // ── Konstanta: status hari ──────────────────────────────────────────────
    // absent / sick / leave BELUM dipakai — memerlukan modul Cuti agar alpa
    // dapat dibedakan dari cuti. Didaftarkan supaya nilainya sudah baku saat
    // modul itu dibangun.

    public const STATUS_PRESENT    = 'present';
    public const STATUS_LATE       = 'late';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_HOLIDAY    = 'holiday';
    public const STATUS_WEEKEND    = 'weekend';
    public const STATUS_ABSENT     = 'absent';
    public const STATUS_SICK       = 'sick';
    public const STATUS_LEAVE      = 'leave';

    public const DAY_STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_INCOMPLETE,
        self::STATUS_HOLIDAY,
        self::STATUS_WEEKEND,
    ];

    // ── Konstanta: sumber data ──────────────────────────────────────────────

    public const SOURCE_ESS         = 'ess_login';
    public const SOURCE_FINGERPRINT = 'fingerprint_excel';
    public const SOURCE_MANUAL_HR   = 'manual_hr';
    public const SOURCE_CORRECTION  = 'correction';

    // ── Konstanta: flag anomali ─────────────────────────────────────────────

    // Flag yang BERSISI diberi awalan 'in:' atau 'out:' oleh AttendanceService,
    // mis. 'in:low_accuracy'. Vonis di dalam/di luar radius TIDAK disimpan di
    // sini — tempatnya di kolom match_type (lihat di atas).
    public const FLAG_NO_COORDINATES    = 'no_coordinates';
    public const FLAG_NO_LOCATION_SETUP = 'no_location_setup';
    public const FLAG_LOW_ACCURACY      = 'low_accuracy';
    public const FLAG_FAR_OUTSIDE       = 'far_outside';
    public const FLAG_CLOCK_SKEW        = 'clock_skew';
    public const FLAG_AUTO_CLOSED       = 'auto_closed';
    public const FLAG_CORRECTED         = 'corrected';
    public const FLAG_MANUAL_HR         = 'manual_hr';

    /** Nama flag bersisi, mis. sideFlag('in', FLAG_LOW_ACCURACY) => 'in:low_accuracy'. */
    public static function sideFlag(string $side, string $flag): string
    {
        return ($side === 'check_out' || $side === 'out' ? 'out' : 'in') . ':' . $flag;
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function checkInBranch()
    {
        return $this->belongsTo(Branch::class, 'check_in_branch_id', 'id');
    }

    public function checkOutBranch()
    {
        return $this->belongsTo(Branch::class, 'check_out_branch_id', 'id');
    }

    public function checkInProjectSite()
    {
        return $this->belongsTo(ProjectSite::class, 'check_in_project_site_id', 'id');
    }

    public function checkOutProjectSite()
    {
        return $this->belongsTo(ProjectSite::class, 'check_out_project_site_id', 'id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id', 'id');
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceCorrection::class, 'attendance_record_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('attendance_date', $date);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function scopeFlagged($query)
    {
        return $query->whereNotNull('flags')->where('flags', '!=', '[]');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /**
     * Rangkaian metadata lokasi untuk ditampilkan di rekap, mis.
     * "IP 182.3.36.215 | GPS -6.238262, 106.789771 | Accuracy 14 m | Status gps_ok"
     *
     * Dirakit di model, bukan di Blade, supaya rekap harian dan ekspor Excel
     * menghasilkan kalimat yang sama persis tanpa menduplikasi logika.
     */
    public function locationSummary(string $side = 'check_in'): string
    {
        $parts = [];

        if ($ip = $this->{$side . '_ip'}) {
            $parts[] = 'IP ' . $ip;
        }

        $lat = $this->{$side . '_latitude'};
        $lng = $this->{$side . '_longitude'};
        if ($lat !== null && $lng !== null) {
            $parts[] = 'GPS ' . rtrim(rtrim(number_format((float) $lat, 6, '.', ''), '0'), '.')
                . ', ' . rtrim(rtrim(number_format((float) $lng, 6, '.', ''), '0'), '.');
        }

        if (($acc = $this->{$side . '_accuracy_m'}) !== null) {
            $parts[] = 'Accuracy ' . (int) round((float) $acc) . ' m';
        }

        if ($conn = $this->{$side . '_connection'}) {
            $parts[] = 'Connection ' . $conn;
        }

        if ($status = $this->{$side . '_gps_status'}) {
            $parts[] = 'Status ' . $status;
        }

        return implode(' | ', $parts);
    }

    /**
     * Kalimat vonis geofence, mis. "Inside office radius (21 m)".
     * Mengembalikan null bila belum ada punch pada sisi tersebut.
     */
    public function geofenceVerdict(string $side = 'check_in'): ?string
    {
        if (!$this->{$side . '_at'}) {
            return null;
        }

        $match = $this->{$side . '_match_type'};

        if ($match === self::MATCH_NONE || $match === null) {
            return 'Device GPS is unavailable';
        }

        $meters = (int) round((float) $this->{$side . '_distance_m'});
        $label  = str_starts_with($match, 'project') ? 'project' : 'office';
        $verdict = str_ends_with($match, '_out') ? 'Outside' : 'Inside';

        return "{$verdict} {$label} radius ({$meters} m)";
    }
}

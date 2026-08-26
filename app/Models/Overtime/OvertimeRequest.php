<?php

namespace App\Models\Overtime;

use App\Models\Attendance\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan lembur beserta hasil akhirnya.
 *
 * Satu baris = satu pengajuan. Beberapa pengajuan pada tanggal yang sama
 * diizinkan selama jamnya tidak tumpang tindih.
 */
class OvertimeRequest extends Model
{
    protected $table = 'overtime_requests';

    protected $fillable = [
        'request_no', 'employee_id',
        'overtime_date', 'start_time', 'end_time', 'crosses_midnight', 'duration_minutes',
        'original_start_time', 'original_end_time', 'adjusted_by',
        'day_type', 'reason',
        'status', 'current_step_order',
        'attendance_record_id', 'attendance_overtime_minutes',
        'hourly_rate', 'rate_breakdown', 'amount',
        'flags', 'notes',
        'period_year', 'period_month',
        'cancelled_at', 'completed_at',
    ];

    protected $casts = [
        'overtime_date'               => 'date',
        'crosses_midnight'            => 'boolean',
        'duration_minutes'            => 'integer',
        'current_step_order'          => 'integer',
        'attendance_overtime_minutes' => 'integer',
        'rate_breakdown'              => 'array',
        'flags'                       => 'array',
        'period_year'                 => 'integer',
        'period_month'                => 'integer',
        'cancelled_at'                => 'datetime',
        'completed_at'                => 'datetime',
    ];

    // ── Status ──────────────────────────────────────────────────────────────

    /** Baru diajukan, belum ada satu langkah pun yang bertindak. */
    public const STATUS_SUBMITTED = 'submitted';

    /** Sudah lulus minimal satu langkah, masih menunggu langkah berikutnya. */
    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /** Status yang masih berjalan — masih menunggu tindakan seseorang. */
    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
    ];

    // ── Jenis hari ──────────────────────────────────────────────────────────
    //
    // Dibekukan saat pengajuan. Menghitung ulang di kemudian hari akan mengubah
    // makna riwayat, karena tabel `holidays` dapat diperbaiki atau bertambah.

    public const DAY_WORKDAY        = 'workday';
    public const DAY_WEEKEND        = 'weekend';
    public const DAY_PUBLIC_HOLIDAY = 'public_holiday';

    public const DAY_TYPES = [
        self::DAY_WORKDAY,
        self::DAY_WEEKEND,
        self::DAY_PUBLIC_HOLIDAY,
    ];

    public const DAY_TYPE_LABELS = [
        self::DAY_WORKDAY        => 'Workday',
        self::DAY_WEEKEND        => 'Weekend',
        self::DAY_PUBLIC_HOLIDAY => 'Public Holiday',
    ];

    // ── Flags ───────────────────────────────────────────────────────────────

    /** Selisih klaim vs catatan absensi melewati ambang toleransi. */
    public const FLAG_DURATION_MISMATCH = 'duration_mismatch';

    /** Tidak ada catatan presensi sama sekali pada tanggal itu. */
    public const FLAG_NO_ATTENDANCE = 'no_attendance_record';

    /** Tanggalnya belum terjadi — pembanding absensi sengaja dilewati. */
    public const FLAG_FUTURE_CLAIM = 'future_claim';

    public const FLAG_BELOW_MIN_DURATION = 'below_min_duration';
    public const FLAG_EXCEEDS_DAILY      = 'exceeds_daily_limit';
    public const FLAG_EXCEEDS_WEEKLY     = 'exceeds_weekly_limit';

    /** Disetujui oleh pemohonnya sendiri — selalu ditandai meski diizinkan. */
    public const FLAG_SELF_APPROVED = 'self_approved';

    /** Jam disesuaikan penyetuju; nilai semula ada di original_*. */
    public const FLAG_TIME_ADJUSTED = 'time_adjusted';

    /** Diproses saat periodenya sudah dikunci. */
    public const FLAG_LOCKED_PERIOD = 'locked_period';

    public const FLAG_LABELS = [
        self::FLAG_DURATION_MISMATCH  => 'Duration differs from attendance',
        self::FLAG_NO_ATTENDANCE      => 'No attendance record',
        self::FLAG_FUTURE_CLAIM       => 'Future date',
        self::FLAG_BELOW_MIN_DURATION => 'Below minimum duration',
        self::FLAG_EXCEEDS_DAILY      => 'Exceeds daily limit',
        self::FLAG_EXCEEDS_WEEKLY     => 'Exceeds weekly limit',
        self::FLAG_SELF_APPROVED      => 'Self-approved',
        self::FLAG_TIME_ADJUSTED      => 'Time adjusted by approver',
        self::FLAG_LOCKED_PERIOD      => 'Locked period',
    ];

    /**
     * Flag yang benar-benar perlu perhatian HR.
     *
     * `future_claim` sengaja TIDAK termasuk: ia penanda netral, bukan anomali.
     * Menandai setiap pengajuan ke depan sebagai janggal akan membuat seluruh
     * penandaan kehilangan artinya.
     */
    public const ATTENTION_FLAGS = [
        self::FLAG_DURATION_MISMATCH,
        self::FLAG_NO_ATTENDANCE,
        self::FLAG_BELOW_MIN_DURATION,
        self::FLAG_EXCEEDS_DAILY,
        self::FLAG_EXCEEDS_WEEKLY,
        self::FLAG_SELF_APPROVED,
        self::FLAG_LOCKED_PERIOD,
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function adjuster()
    {
        return $this->belongsTo(Employee::class, 'adjusted_by', 'employee_id');
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id', 'id');
    }

    public function approvals()
    {
        return $this->hasMany(OvertimeRequestApproval::class, 'overtime_request_id', 'id')
                    ->orderBy('order_seq');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** Hanya pengajuan yang belum disentuh siapa pun yang boleh dibatalkan. */
    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags ?? [], true);
    }

    /** Flag yang perlu perhatian HR — dipakai mewarnai baris di rekap. */
    public function attentionFlags(): array
    {
        return array_values(array_intersect($this->flags ?? [], self::ATTENTION_FLAGS));
    }

    public function dayTypeLabel(): string
    {
        return self::DAY_TYPE_LABELS[$this->day_type] ?? $this->day_type;
    }

    /** "3 h 15 m" — dipakai di seluruh tampilan supaya formatnya tidak bercabang. */
    public function durationLabel(): string
    {
        return self::formatMinutes($this->duration_minutes);
    }

    public static function formatMinutes(?int $minutes): string
    {
        $minutes = max(0, (int) $minutes);
        $hours   = intdiv($minutes, 60);
        $rest    = $minutes % 60;

        if ($hours === 0) {
            return $rest . ' m';
        }

        return $rest === 0 ? $hours . ' h' : $hours . ' h ' . $rest . ' m';
    }

    /**
     * Label status untuk layar.
     *
     * Nilai `status` sengaja tetap terbatas pada lima kemungkinan, tetapi label
     * yang dibaca pengguna mengambil NAMA LANGKAH terakhir yang lulus — sehingga
     * muncul "Approved by Admin Approval" tanpa menambah nilai status baru
     * setiap kali langkah persetujuan berubah.
     */
    public function statusLabel(): string
    {
        if ($this->status !== self::STATUS_IN_REVIEW) {
            return ucfirst(str_replace('_', ' ', $this->status));
        }

        $lastApproved = $this->approvals
            ->where('status', OvertimeRequestApproval::STATUS_APPROVED)
            ->sortByDesc('order_seq')
            ->first();

        return $lastApproved
            ? 'Approved by ' . $lastApproved->step_name
            : 'In review';
    }

    /** Langkah yang sedang menunggu tindakan, bila masih ada. */
    public function currentApproval(): ?OvertimeRequestApproval
    {
        if (!$this->isOpen() || $this->current_step_order === null) {
            return null;
        }

        return $this->approvals->firstWhere('order_seq', $this->current_step_order);
    }
}

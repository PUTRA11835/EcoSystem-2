<?php

namespace App\Models\Attendance;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan koreksi jam presensi beserta persetujuannya.
 */
class AttendanceCorrection extends Model
{
    protected $table = 'attendance_corrections';

    protected $fillable = [
        'attendance_record_id', 'employee_id', 'attendance_date',
        'requested_check_in', 'requested_check_out', 'reason',
        'original_check_in', 'original_check_out',
        'status', 'approved_by', 'approved_by_type', 'approved_at', 'hr_note',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'approved_at'     => 'datetime',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Jenis penyetuju. 'supervisor' disediakan untuk saat hierarki atasan
     * dibangun; MVP hanya memakai 'hr'.
     */
    public const APPROVER_HR         = 'hr';
    public const APPROVER_SUPERVISOR = 'supervisor';

    // ── Relationships ───────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function record()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}

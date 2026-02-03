<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Timesheet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'project_id',
        'activity_id', // Link to assigned activity
        'ticket_id',
        'date',
        'start_time',
        'end_time',
        'duration_minutes',
        'description',
        'activity_type',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'is_billable',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
        'is_billable' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($timesheet) {
            if ($timesheet->start_time && $timesheet->end_time) {
                $start = Carbon::parse($timesheet->start_time);
                $end = Carbon::parse($timesheet->end_time);
                $timesheet->duration_minutes = $end->diffInMinutes($start);
            }
        });
    }

    // UPDATE: Gunakan employee_id sebagai foreign key
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the activity associated with this timesheet
     */
    public function activity()
    {
        return $this->belongsTo(ProjectActivity::class, 'activity_id');
    }

    // Scopes...
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeForActivity($query, $activityId)
    {
        return $query->where('activity_id', $activityId);
    }

    public function getFormattedDurationAttribute()
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;
        return sprintf('%d:%02d', $hours, $minutes);
    }

    public function getDurationHoursAttribute()
    {
        return round($this->duration_minutes / 60, 2);
    }
}
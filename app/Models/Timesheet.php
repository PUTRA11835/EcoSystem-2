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
        'delivery_projects_id', // Changed from project_id to match database column
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
        'presence',
        'location',
        'md_consumed',
        'period_year',
        'period_month',
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
                // Parse times - handle both HH:mm and HH:mm:ss formats
                $start = Carbon::parse($timesheet->start_time);
                $end = Carbon::parse($timesheet->end_time);

                // If end time is before start time (overnight work), add 24 hours
                if ($end->lt($start)) {
                    $end->addDay();
                }

                // Calculate duration (always positive)
                $timesheet->duration_minutes = $start->diffInMinutes($end);
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

    public function delivery_project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    /**
     * Get the activity associated with this timesheet
     */
    public function activity()
    {
        return $this->belongsTo(DeliveryProjectActivity::class, 'activity_id');
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
        return $query->where('delivery_projects_id', $projectId);
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
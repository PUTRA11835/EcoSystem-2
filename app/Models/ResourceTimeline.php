<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceTimeline extends Model
{
    use HasFactory;

    // Not Auditable on purpose: rows are per-day, so the Audit Log would get one row
    // per day (and bulk deletes bypass model events). ResourceTimelineService writes
    // one range-level Audit Log row per action instead.

    protected $fillable = [
        'employee_id',
        'date',
        'location',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}

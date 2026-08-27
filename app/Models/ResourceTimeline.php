<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceTimeline extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Reporting';

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

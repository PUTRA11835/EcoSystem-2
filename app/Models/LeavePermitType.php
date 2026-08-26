<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeavePermitType extends Model
{
    protected $table = 'leave_permit_types';

    protected $fillable = [
        'code',
        'name',
        'category',
        'default_quota',
        'min_service_period',
        'is_paid',
        'gender_target',
        'requires_attachment',
        'description',
        'is_active',
    ];

    protected $casts = [
        'default_quota' => 'float',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function quotas()
    {
        return $this->hasMany(LeavePermitQuota::class, 'leave_permit_type_id');
    }

    public function applications()
    {
        return $this->hasMany(LeavePermitApplication::class, 'leave_permit_type_id');
    }
}

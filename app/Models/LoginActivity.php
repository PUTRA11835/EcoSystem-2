<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginActivity extends Model
{
    protected $table = 'login_activity';
    protected $primaryKey = 'activity_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
        'user_name',
        'user_type',
        'ip_address',
        'user_agent',
        'device_type',
        'device_brand',
        'device_model',
        'browser',
        'os',
        'location_city',
        'location_country',
        'login_at',
        'logout_at',
        'status',
        'created_at',
    ];

    protected $casts = [
        'login_at'   => 'datetime',
        'logout_at'  => 'datetime',
        'created_at' => 'datetime',
    ];
}

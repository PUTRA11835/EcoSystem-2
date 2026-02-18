<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AuthUser extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $table = 'auth_users';

    protected $fillable = [
        'user_type',
        'employee_id',
        'customer_id',
        'username',
        'email',
        'phone',
        'password',
        'last_login_at',
        'is_active',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function getNameAttribute()
    {
        return $this->username ?? $this->email;
    }

    public function getEciAttribute()
    {
        return $this->employee?->eci;
    }
}

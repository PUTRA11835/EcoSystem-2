<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\CustomerContact;

class AuthUser extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use HasApiTokens;

    protected $table = 'auth_users';

    protected $fillable = [
        'employee_id',
        'customer_id',
        'contact_id',
        'username',
        'email',
        'phone',
        'password',
        'last_login_at',
        'is_active',
        'is_already_cp',
        'can_view_all_tickets',
        'preferences',
        'cp_token',
        'cp_token_expires_at',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'cp_token',           // jangan ekspos token di response JSON
    ];

    protected $casts = [
        'last_login_at'       => 'datetime',
        'is_active'               => 'boolean',
        'is_already_cp'           => 'boolean',
        'can_view_all_tickets'    => 'boolean',
        'preferences'             => 'array',
        'cp_token_expires_at' => 'datetime',
        'email_verified_at'   => 'datetime',
        'phone_verified_at'   => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function contact()
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id', 'contact_id');
    }

    public function getNameAttribute()
    {
        return $this->username ?? $this->email;
    }

    public function getUserTypeAttribute(): string
    {
        if (!is_null($this->employee_id)) {
            return 'employee';
        }
        if (!is_null($this->customer_id)) {
            return 'customer';
        }
        return 'unknown';
    }

    public function isEmployee(): bool
    {
        return !is_null($this->employee_id);
    }

    public function isCustomer(): bool
    {
        return !is_null($this->customer_id);
    }

    public function getEciAttribute()
    {
        return $this->employee?->eci;
    }
}

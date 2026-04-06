<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeAddress extends Model
{
    use HasFactory;

    protected $table = 'employee_address';
    protected $primaryKey = 'address_id';

    protected $fillable = [
        'employee_id',
        
        // 🏠 ADDRESS INFORMATION
        'address_type',
        'country',
        'region',
        'city',
        'district',
        'rural_urban_village',
        'street',
        'house_number',
        'postal_code',
        
        // 📞 COMMUNICATION
        'language',
        'cell_phone_country',
        'telephone_country',
        'fax_country',
        'preferred_communication',
        'cell_phone',
        'telephone',
        'telephone_extension',
        'fax',
        'fax_extension',
        'email_personal',
        'email_work',
        'website',
        
        // ⏳ VALIDITY PERIOD
        'valid_from',
        'valid_to',
        
        // ⚙️ STATUS
        'is_primary',
        'is_verified',
    ];

    // Tipe data cast otomatis
    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
    ];

    /**
     * Relasi ke model Employee
     * Satu alamat dimiliki oleh satu karyawan
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Scope untuk mendapatkan alamat primary
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope untuk mendapatkan alamat yang verified
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope untuk mendapatkan alamat yang masih valid
     */
    public function scopeValid($query)
    {
        $today = now()->toDateString();
        return $query->where(function($q) use ($today) {
            $q->whereNull('valid_from')
              ->orWhere('valid_from', '<=', $today);
        })->where(function($q) use ($today) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', $today);
        });
    }

    /**
     * Accessor untuk full address
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->street,
            $this->house_number,
            $this->rural_urban_village,
            $this->district,
            $this->city,
            $this->region,
            $this->postal_code,
            $this->country,
        ]);
        
        return implode(', ', $parts);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeIdentification extends Model
{
    use HasFactory;

    // Nama tabel
    protected $table = 'employee_identification';

    // Primary key
    protected $primaryKey = 'identification_id';

    // Kolom yang bisa diisi secara massal
    protected $fillable = [
        'employee_id',
        'identification_type',
        'identification_number',
        'responsible_institution',
        'country',
        'region',
        'valid_from',
        'valid_to',
        'entry_date',
        'drive_link',
        'verify_link',
    ];

    // Casting tipe data
    protected $casts = [
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
        'entry_date' => 'date:Y-m-d',
    ];

    /**
     * Relasi ke model Employee
     * Satu identification dimiliki oleh satu karyawan
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Accessor untuk mendapatkan status validitas
     */
    public function getIsValidAttribute(): bool
    {
        if (!$this->valid_to) {
            return true; // Jika tidak ada tanggal expired, dianggap valid
        }
        
        return now()->lte($this->valid_to);
    }

    /**
     * Accessor untuk mendapatkan sisa hari valid
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->valid_to) {
            return null;
        }
        
        return now()->diffInDays($this->valid_to, false);
    }

    /**
     * Scope untuk filter identification yang masih valid
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', now());
        });
    }

    /**
     * Scope untuk filter identification yang sudah expired
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('valid_to')
                     ->where('valid_to', '<', now());
    }

    /**
     * Scope untuk filter identification yang akan expired dalam X hari
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->whereNotNull('valid_to')
                     ->where('valid_to', '>', now())
                     ->where('valid_to', '<=', now()->addDays($days));
    }
}
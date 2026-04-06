<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmployeeContract extends Model
{
    use HasFactory;

    // Nama tabel
    protected $table = 'employee_contract';

    // Primary key
    protected $primaryKey = 'contract_id';

    // Field yang dapat diisi secara massal
    protected $fillable = [
        'employee_id',
        'contract_number',
        'contract_name',
        'contract_type',
        'contract_date',
        'position',
        'salary',
        'start_date',
        'end_date',
        'is_active',
        'drive_link',
        'verify_link',
    ];

    // Casting tipe data otomatis
    protected $casts = [
        'salary' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'contract_date' => 'datetime',
    ];

    /**
     * Relasi ke model Employee
     * Satu kontrak dimiliki oleh satu karyawan
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Scope untuk filter kontrak aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk filter kontrak tidak aktif
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope untuk filter berdasarkan tipe kontrak
     */
    public function scopeByContractType($query, $type)
    {
        return $query->where('contract_type', $type);
    }

    /**
     * Scope untuk kontrak permanent
     */
    public function scopePermanent($query)
    {
        return $query->where('contract_type', 'Permanent');
    }

    /**
     * Scope untuk kontrak yang akan berakhir (expiring soon)
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        $futureDate = now()->addDays($days);
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<=', $futureDate)
                    ->where('end_date', '>=', now());
    }

    /**
     * Scope untuk kontrak yang sudah expired
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<', now());
    }

    /**
     * Scope untuk kontrak yang masih berlaku (valid)
     */
    public function scopeValid($query)
    {
        return $query->where(function($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
        });
    }

    /**
     * Scope untuk kontrak dalam periode tertentu
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->where(function($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where(function($q3) use ($endDate) {
                         $q3->where('end_date', '>=', $endDate)
                            ->orWhereNull('end_date');
                     });
              });
        });
    }

    /**
     * Accessor untuk mendapatkan periode kontrak
     */
    public function getPeriodAttribute()
    {
        $start = $this->start_date ? $this->start_date->format('d M Y') : '?';
        $end = $this->end_date ? $this->end_date->format('d M Y') : 'Permanent';
        return "{$start} - {$end}";
    }

    /**
     * Accessor untuk mendapatkan durasi kontrak (dalam hari)
     */
    public function getDurationInDaysAttribute()
    {
        if (!$this->start_date) {
            return null;
        }
        
        $endDate = $this->end_date ?? now();
        return $this->start_date->diffInDays($endDate);
    }

    /**
     * Accessor untuk mendapatkan durasi kontrak (dalam bulan)
     */
    public function getDurationInMonthsAttribute()
    {
        if (!$this->start_date) {
            return null;
        }
        
        $endDate = $this->end_date ?? now();
        return $this->start_date->diffInMonths($endDate);
    }

    /**
     * Accessor untuk mendapatkan durasi kontrak (dalam tahun)
     */
    public function getDurationInYearsAttribute()
    {
        if (!$this->start_date) {
            return null;
        }
        
        $endDate = $this->end_date ?? now();
        return $this->start_date->diffInYears($endDate);
    }

    /**
     * Accessor untuk mendapatkan sisa hari kontrak
     */
    public function getRemainingDaysAttribute()
    {
        if (!$this->end_date) {
            return null; // Permanent contract
        }
        
        if ($this->end_date->isPast()) {
            return 0; // Already expired
        }
        
        return now()->diffInDays($this->end_date);
    }

    /**
     * Accessor untuk mendapatkan status kontrak
     */
    public function getStatusAttribute()
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        
        if (!$this->end_date) {
            return 'active_permanent';
        }
        
        if ($this->end_date->isPast()) {
            return 'expired';
        }
        
        if ($this->end_date->lte(now()->addDays(30))) {
            return 'expiring_soon';
        }
        
        return 'active';
    }

    /**
     * Accessor untuk mendapatkan label status
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'inactive' => 'Inactive',
            'active_permanent' => 'Active (Permanent)',
            'expired' => 'Expired',
            'expiring_soon' => 'Expiring Soon',
            'active' => 'Active'
        ];
        
        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Accessor untuk mendapatkan warna badge status
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            'inactive' => 'gray',
            'active_permanent' => 'blue',
            'expired' => 'red',
            'expiring_soon' => 'yellow',
            'active' => 'green'
        ];
        
        return $colors[$this->status] ?? 'gray';
    }

    /**
     * Accessor untuk format salary (Indonesia Rupiah)
     */
    public function getFormattedSalaryAttribute()
    {
        if (!$this->salary) {
            return '-';
        }
        return 'Rp ' . number_format($this->salary, 0, ',', '.');
    }

    /**
     * Accessor untuk format salary (short version)
     */
    public function getShortSalaryAttribute()
    {
        if (!$this->salary) {
            return '-';
        }
        
        $salary = $this->salary;
        if ($salary >= 1000000000) {
            return 'Rp ' . number_format($salary / 1000000000, 1) . 'M';
        } elseif ($salary >= 1000000) {
            return 'Rp ' . number_format($salary / 1000000, 1) . 'Jt';
        } else {
            return 'Rp ' . number_format($salary / 1000, 0) . 'K';
        }
    }

    /**
     * Check apakah kontrak masih valid/berlaku
     */
    public function isValid()
    {
        if (!$this->end_date) {
            return true; // Permanent contract
        }
        return now()->lte($this->end_date);
    }

    /**
     * Check apakah kontrak sudah expired
     */
    public function isExpired()
    {
        if (!$this->end_date) {
            return false; // Permanent contract never expires
        }
        return now()->gt($this->end_date);
    }

    /**
     * Check apakah kontrak akan segera berakhir
     */
    public function isExpiringSoon($days = 30)
    {
        if (!$this->end_date) {
            return false;
        }
        
        $futureDate = now()->addDays($days);
        return $this->end_date->lte($futureDate) && $this->end_date->gte(now());
    }

    /**
     * Check apakah kontrak permanent
     */
    public function isPermanent()
    {
        return $this->end_date === null || $this->contract_type === 'Permanent';
    }

    /**
     * Activate this contract and deactivate others
     */
    public function activate()
    {
        // Deactivate all other contracts for this employee
        static::where('employee_id', $this->employee_id)
            ->where('contract_id', '!=', $this->contract_id)
            ->update(['is_active' => false]);
        
        // Activate this contract
        $this->is_active = true;
        $this->save();
        
        return $this;
    }

    /**
     * Deactivate this contract
     */
    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
        
        return $this;
    }

    /**
     * Renew contract with new end date
     */
    public function renew($newEndDate)
    {
        $this->end_date = $newEndDate;
        $this->save();
        
        return $this;
    }

    /**
     * Extend contract duration
     */
    public function extend($months)
    {
        if ($this->end_date) {
            $this->end_date = $this->end_date->addMonths($months);
        } else {
            $this->end_date = now()->addMonths($months);
        }
        
        $this->save();
        
        return $this;
    }

    /**
     * Mutator untuk contract_type (Title Case)
     */
    public function setContractTypeAttribute($value)
    {
        $this->attributes['contract_type'] = $value ? ucwords(strtolower($value)) : null;
    }

    /**
     * Mutator untuk contract_number (Uppercase)
     */
    public function setContractNumberAttribute($value)
    {
        $this->attributes['contract_number'] = $value ? strtoupper($value) : null;
    }

    /**
     * Boot method untuk auto-deactivate other contracts
     */
    protected static function boot()
    {
        parent::boot();
        
        // When creating a new active contract, deactivate others
        static::creating(function ($contract) {
            if ($contract->is_active) {
                static::where('employee_id', $contract->employee_id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
        
        // When updating to active, deactivate others
        static::updating(function ($contract) {
            if ($contract->is_active && $contract->isDirty('is_active')) {
                static::where('employee_id', $contract->employee_id)
                    ->where('contract_id', '!=', $contract->contract_id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });
    }
}
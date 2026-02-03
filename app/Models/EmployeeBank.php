<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBank extends Model
{
    use HasFactory;

    protected $table = 'employee_bank';
    protected $primaryKey = 'bank_id';
    
    protected $fillable = [
        'employee_id',
        'bank_name',
        'bank_key',
        'account_number',
        'account_holder',
        'drive_link',
        'valid_from',
        'valid_to',
        'verify_link',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship with Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Check if bank account is currently valid
     */
    public function isValid()
    {
        $today = now()->startOfDay();
        
        if ($this->valid_from && $this->valid_to) {
            return $today->between($this->valid_from, $this->valid_to);
        }
        
        if ($this->valid_from && !$this->valid_to) {
            return $today->greaterThanOrEqualTo($this->valid_from);
        }
        
        if (!$this->valid_from && $this->valid_to) {
            return $today->lessThanOrEqualTo($this->valid_to);
        }
        
        return true;
    }

    /**
     * Check if bank account is expired
     */
    public function isExpired()
    {
        if (!$this->valid_to) {
            return false;
        }
        
        return now()->startOfDay()->greaterThan($this->valid_to);
    }

    /**
     * Check if bank account is expiring soon (within 30 days)
     */
    public function isExpiringSoon()
    {
        if (!$this->valid_to) {
            return false;
        }
        
        $today = now()->startOfDay();
        $thirtyDaysLater = now()->addDays(30)->startOfDay();
        
        return $this->valid_to->between($today, $thirtyDaysLater);
    }

    /**
     * Get masked account number for security
     */
    public function getMaskedAccountNumberAttribute()
    {
        if (!$this->account_number) {
            return null;
        }
        
        $accountNumber = $this->account_number;
        $length = strlen($accountNumber);
        
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        return str_repeat('*', $length - 4) . substr($accountNumber, -4);
    }

    /**
     * Scope for valid bank accounts
     */
    public function scopeValid($query)
    {
        $today = now()->startOfDay();
        
        return $query->where(function($q) use ($today) {
            $q->where(function($subQ) use ($today) {
                $subQ->where('valid_from', '<=', $today)
                     ->where(function($validToQ) use ($today) {
                         $validToQ->where('valid_to', '>=', $today)
                                  ->orWhereNull('valid_to');
                     });
            })
            ->orWhere(function($subQ) use ($today) {
                $subQ->whereNull('valid_from')
                     ->where(function($validToQ) use ($today) {
                         $validToQ->where('valid_to', '>=', $today)
                                  ->orWhereNull('valid_to');
                     });
            })
            ->orWhere(function($subQ) {
                $subQ->whereNull('valid_from')
                     ->whereNull('valid_to');
            });
        });
    }

    /**
     * Scope for expired bank accounts
     */
    public function scopeExpired($query)
    {
        return $query->where('valid_to', '<', now()->startOfDay())
                    ->whereNotNull('valid_to');
    }

    /**
     * Scope for expiring soon (within 30 days)
     */
    public function scopeExpiringSoon($query)
    {
        $today = now()->startOfDay();
        $thirtyDaysLater = now()->addDays(30)->startOfDay();
        
        return $query->whereBetween('valid_to', [$today, $thirtyDaysLater]);
    }
}
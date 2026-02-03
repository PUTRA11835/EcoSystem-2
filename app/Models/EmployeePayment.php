<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayment extends Model
{
    use HasFactory;

    protected $table = 'employee_payment';
    protected $primaryKey = 'payment_id';
    
    protected $fillable = [
        'employee_id',
        'amount',
        'paid_at',
        'payment_method',
        'reference_number',
        'payment_status',
        'valid_to',
        'drive_link',
        'verify_link',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
        'valid_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Payment status constants
    const STATUS_PENDING = 'Pending';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_FAILED = 'Failed';
    const STATUS_PROCESSING = 'Processing';
    const STATUS_CANCELLED = 'Cancelled';

    // Payment method constants
    const METHOD_TRANSFER = 'Transfer';
    const METHOD_CASH = 'Cash';
    const METHOD_E_WALLET = 'E-Wallet';
    const METHOD_CHEQUE = 'Cheque';
    const METHOD_CREDIT_CARD = 'Credit Card';

    /**
     * Relationship with Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted()
    {
        return $this->payment_status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending()
    {
        return $this->payment_status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is failed
     */
    public function isFailed()
    {
        return $this->payment_status === self::STATUS_FAILED;
    }

    /**
     * Check if payment is processing
     */
    public function isProcessing()
    {
        return $this->payment_status === self::STATUS_PROCESSING;
    }

    /**
     * Check if payment is valid (not expired)
     */
    public function isValid()
    {
        if (!$this->valid_to) {
            return true;
        }
        
        return now()->startOfDay()->lessThanOrEqualTo($this->valid_to);
    }

    /**
     * Check if payment is expired
     */
    public function isExpired()
    {
        if (!$this->valid_to) {
            return false;
        }
        
        return now()->startOfDay()->greaterThan($this->valid_to);
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->amount, 2, ',', '.');
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->payment_status) {
            self::STATUS_COMPLETED => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray'
        };
    }

    /**
     * Scope for completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('payment_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('payment_status', self::STATUS_FAILED);
    }

    /**
     * Scope for processing payments
     */
    public function scopeProcessing($query)
    {
        return $query->where('payment_status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for valid payments (not expired)
     */
    public function scopeValid($query)
    {
        return $query->where(function($q) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>=', now()->startOfDay());
        });
    }

    /**
     * Scope for expired payments
     */
    public function scopeExpired($query)
    {
        return $query->where('valid_to', '<', now()->startOfDay())
                    ->whereNotNull('valid_to');
    }

    /**
     * Scope for payments by method
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope for payments in date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
    }

    /**
     * Scope for payments this month
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('paid_at', now()->month)
                    ->whereYear('paid_at', now()->year);
    }

    /**
     * Scope for payments this year
     */
    public function scopeThisYear($query)
    {
        return $query->whereYear('paid_at', now()->year);
    }
}
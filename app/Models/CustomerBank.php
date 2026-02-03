<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBank extends Model
{
    protected $table = 'customer_bank';
    protected $primaryKey = 'bank_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
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
     * Get the customer that owns the bank account
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
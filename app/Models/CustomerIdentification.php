<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerIdentification extends Model
{
    protected $table = 'customer_identification';
    protected $primaryKey = 'identification_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'identification_type',
        'identification_number',
        'responsible_institution',
        'country',
        'region',
        'entry_date',
        'valid_from',
        'valid_to',
        'drive_link',
        'verify_link',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the identification
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
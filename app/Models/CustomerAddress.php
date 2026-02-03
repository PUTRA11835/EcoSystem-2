<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $table = 'customer_address';
    protected $primaryKey = 'address_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'address_type',
        'country',
        'region',
        'city',
        'district',
        'rural_urban_village',
        'street',
        'house_number',
        'postal_code',
        'language',
        'cell_phone_country',
        'telephone_country',
        'fax_country',
        'email',
        'website',
        'preferred_communication',
        'cell_phone',
        'telephone',
        'fax',
        'telephone_extension',
        'fax_extension',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the address
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
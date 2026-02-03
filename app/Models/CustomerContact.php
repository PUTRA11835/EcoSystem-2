<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    protected $table = 'customer_contact';
    protected $primaryKey = 'contact_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'title',
        'full_name',
        'nick_name',
        'position',
        'department',
        'valid_from',
        'valid_to',
        'entry_date',
        'language',
        'cell_phone_country',
        'telephone_country',
        'fax_country',
        'email_personal',
        'email_work',
        'website',
        'preferred_communication',
        'cell_phone',
        'telephone',
        'fax',
        'telephone_extension',
        'fax_extension',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'entry_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the contact
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
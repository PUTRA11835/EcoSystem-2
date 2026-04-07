<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCredential extends Model
{
    protected $table = 'customer_credential';
    protected $primaryKey = 'credential_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'notes',
        'updated_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}

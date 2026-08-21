<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class CustomerHistory extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Customer';

    protected $table = 'customer_history';
    protected $primaryKey = 'history_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'action',
        'description',
        'section',
        'user_id',
        'user_name',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the customer that owns the history record
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
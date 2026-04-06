<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerMandaysDetail extends Model
{
    use HasFactory;

    protected $table = 'customer_mandays_detail';

    protected $fillable = [
        'customer_mandays_id',
        'activity',
        'module',
        'mandays',
        'notes',
    ];

    protected $casts = [
        'mandays' => 'decimal:2',
    ];

    public function customerMandays()
    {
        return $this->belongsTo(CustomerMandays::class, 'customer_mandays_id');
    }

    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBasicData extends Model
{
    protected $table = 'customer_basic_data';
    protected $primaryKey = 'basic_data_id';
    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'title',
        'name_1',
        'name_2',
        'search_term_1',
        'external_number',
        'customer_group',
        'customer_category',
        'block',
        'deletion_flag',
    ];

    protected $casts = [
        'block' => 'boolean',
        'deletion_flag' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}

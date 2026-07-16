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
        'search_term_2',
        'external_number',
        'customer_group',
        'customer_category',
        'credit_limit_type',
        'industry_sector',
        'ec_account_executive',
        'sap_account_executive',
        'authorization_group',
        'block',
        'deletion_flag',
        'created_by',
        'created_on',
        'last_changed_by',
        'last_changed_on',
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

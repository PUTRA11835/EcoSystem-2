<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single value belonging to a DropdownConfig (e.g. "SAP CONSULTANT" under
 * the "position" config). See migration create_dropdown_configs_tables.
 */
class DropdownConfigValue extends Model
{
    protected $fillable = ['dropdown_config_id', 'value', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function config()
    {
        return $this->belongsTo(DropdownConfig::class, 'dropdown_config_id');
    }
}

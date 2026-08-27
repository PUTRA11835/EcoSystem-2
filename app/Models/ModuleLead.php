<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleLead extends Model
{
    protected $table = 'module_leads';

    protected $fillable = [
        'module_id',
        'employee_id',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}

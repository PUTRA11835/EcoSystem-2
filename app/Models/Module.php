<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function qualifications()
    {
        return $this->hasMany(EmployeeQualification::class, 'module_id', 'id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_qualification', 'module_id', 'employee_id', 'id', 'employee_id')
                    ->distinct();
    }

    public function leads()
    {
        return $this->hasMany(ModuleLead::class, 'module_id', 'id');
    }

    public function leadEmployees()
    {
        return $this->belongsToMany(Employee::class, 'module_leads', 'module_id', 'employee_id', 'id', 'employee_id')
                    ->withTimestamps();
    }

    public function groups()
    {
        return $this->belongsToMany(ModuleGroup::class, 'module_group_modules', 'module_id', 'module_group_id')
                    ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeRole extends Model
{
    use HasFactory;

    protected $table = 'employee_role';

    protected $fillable = [
        'name',
        'description',
    ];

    public function getRoleIdAttribute()
    {
        return $this->id;
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'role_id', 'id');
    }
}

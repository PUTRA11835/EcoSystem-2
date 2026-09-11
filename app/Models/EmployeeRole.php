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
        return $this->belongsToMany(Employee::class, 'employee_role_assignment', 'role_id', 'employee_id', 'id', 'employee_id')
                    ->withTimestamps();
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'role_menu', 'role_id', 'menu_id')
            ->withPivot('can_view', 'can_create', 'can_edit', 'can_delete')
            ->withTimestamps();
    }
}

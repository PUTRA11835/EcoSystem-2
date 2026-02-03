<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'role';
    protected $primaryKey = 'role_id';
    public $timestamps = false;

    protected $fillable = [
        'role_name',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'role_id', 'role_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'role_id', 'role_id');
    }
}
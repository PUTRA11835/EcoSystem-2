<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $table = 'menu';

    protected $fillable = [
        'parent_id', 'name', 'slug', 'type',
        'route_name', 'icon', 'order_seq', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('order_seq');
    }

    public function roles()
    {
        return $this->belongsToMany(EmployeeRole::class, 'role_menu', 'menu_id', 'role_id')
            ->withPivot('can_view', 'can_create', 'can_edit', 'can_delete')
            ->withTimestamps();
    }
}

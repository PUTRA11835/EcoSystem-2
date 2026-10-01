<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Menu favorit sidebar milik satu karyawan (lihat migrasi
 * 2026_09_30_000002_create_user_menu_favorites_table untuk alasan desainnya).
 */
class UserMenuFavorite extends Model
{
    protected $table = 'user_menu_favorites';

    protected $fillable = [
        'employee_id',
        'menu_path',
        'sort_order',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'sort_order'  => 'integer',
    ];

    // -- Relationships --

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}

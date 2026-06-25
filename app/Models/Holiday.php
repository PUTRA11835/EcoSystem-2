<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'date',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'date'      => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public const TYPES = ['national', 'cuti_bersama', 'custom'];
}

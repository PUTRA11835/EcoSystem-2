<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSound extends Model
{
    protected $fillable = ['name', 'filename', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function getUrlAttribute(): string
    {
        return '/sounds/' . $this->filename;
    }
}

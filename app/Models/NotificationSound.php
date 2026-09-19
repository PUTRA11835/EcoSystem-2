<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class NotificationSound extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Notification Sounds';

    protected $fillable = ['name', 'filename', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function getUrlAttribute(): string
    {
        return '/sounds/' . $this->filename;
    }
}

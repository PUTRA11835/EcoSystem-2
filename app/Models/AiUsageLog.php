<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_usage_logs';

    protected $guarded = [];

    protected $casts = [
        'cost_usd' => 'float',
    ];
}

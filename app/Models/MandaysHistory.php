<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MandaysHistory extends Model
{
    use HasFactory;
    
    protected $table = 'mandays_history';
    protected $primaryKey = 'history_id';
    
    protected $fillable = [
        'ticket_id',
        'old_mandays',
        'new_mandays',
        'changed_by',
        'changed_by_role',
        'reason'
    ];

    protected $casts = [
        'old_mandays' => 'decimal:2',
        'new_mandays' => 'decimal:2'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
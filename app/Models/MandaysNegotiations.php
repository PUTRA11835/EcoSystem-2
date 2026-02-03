<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MandaysNegotiation extends Model
{
    use HasFactory;
    
    protected $table = 'mandays_negotiations';
    protected $primaryKey = 'negotiation_id';
    
    protected $fillable = [
        'ticket_id',
        'proposed_mandays',
        'proposed_by',
        'proposed_by_user_id',
        'notes',
        'status',
        'responded_at'
    ];

    protected $casts = [
        'proposed_mandays' => 'decimal:2',
        'responded_at' => 'datetime'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
    
    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }
}
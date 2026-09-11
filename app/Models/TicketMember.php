<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TicketMember extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Ticket';

    protected $table = 'ticket_member';
    protected $primaryKey = 'ticket_member_id';

    protected $fillable = [
        'ticket_id',
        'employee_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}

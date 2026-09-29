<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris tiket dalam batch Weekly Consolidation. Hanya `ticket_id` + notes
 * yang disimpan di sini — status/progress/PIC/team lead/member SELALU dibaca
 * live lewat relasi ticket() saat ditampilkan/di-export, tidak pernah disalin
 * ke kolom sendiri. Jangan tergoda menambah kolom snapshot di sini.
 */
class WeeklyConsolidationTicket extends Model
{
    protected $table = 'weekly_consolidation_tickets';

    protected $fillable = [
        'weekly_consolidation_id',
        'ticket_id',
        'notes',
        'notes_updated_by_id',
        'notes_updated_at',
    ];

    protected $casts = [
        'notes_updated_at' => 'datetime',
    ];

    public function consolidation()
    {
        return $this->belongsTo(WeeklyConsolidation::class, 'weekly_consolidation_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function notesUpdatedBy()
    {
        return $this->belongsTo(Employee::class, 'notes_updated_by_id', 'employee_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketDeliverable extends Model
{
    protected $table = 'ticket_deliverables';

    protected $fillable = [
        'ticket_id',
        'doc_type',
        'body_text',
        'file_name',
        'onedrive_file_id',
        'onedrive_file_url',
        'status',
        'uploaded_by',
        'upload_date',
    ];

    protected $casts = [
        'upload_date' => 'date',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function uploader()
    {
        return $this->belongsTo(Employee::class, 'uploaded_by', 'employee_id');
    }
}

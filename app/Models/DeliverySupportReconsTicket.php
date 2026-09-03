<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris tiket di dalam satu batch Recons.
 *
 * `man_days_snapshot` sengaja menyimpan salinan `ticket.man_days` saat tiket
 * dimasukkan, supaya angka pada Recons yang sudah disubmit tidak ikut berubah
 * kalau man_days tiketnya diedit belakangan.
 */
class DeliverySupportReconsTicket extends Model
{
    use HasFactory;

    protected $table = 'delivery_support_recons_tickets';

    protected $fillable = [
        'delivery_support_recons_id',
        'ticket_id',
        'man_days_snapshot',
    ];

    protected $casts = [
        'man_days_snapshot' => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function recons()
    {
        return $this->belongsTo(DeliverySupportRecons::class, 'delivery_support_recons_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}

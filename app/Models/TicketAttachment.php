<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $table = 'ticket_attachment';

    protected $fillable = [
        'ticket_id',
        'message_id',
        'uploaded_by_type',
        'uploaded_by_id',
        'attachment_type',
        'link_url',
        'link_title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'is_inline',
        'graph_attachment_id',
        'graph_message_id',
        'content_id',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
    ];

    /**
     * URL publik untuk download/tampil file.
     *
     * Prioritas:
     * 1. Record baru (graph_message_id tersedia) → proxy route /attachments/{id}
     *    Fetch file langsung dari Microsoft Graph saat dibutuhkan (hemat storage)
     * 2. Record lama (file_path tersedia) → path relatif /storage/...
     * 3. Fallback → link_url dari DB
     */
    public function getPublicUrlAttribute(): ?string
    {
        // Record baru: file tidak disimpan lokal, ambil dari Graph via proxy
        if ($this->graph_message_id && $this->graph_attachment_id) {
            return route('attachments.show', $this->id);
        }

        // Record lama: file sudah disimpan lokal sebelum strategi berubah
        if ($this->file_path) {
            return '/storage/' . $this->file_path;
        }

        return $this->link_url;
    }

    /**
     * Apakah attachment ini berupa gambar (bisa ditampilkan inline).
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function message()
    {
        return $this->belongsTo(TicketMessage::class, 'message_id');
    }

    public function uploader()
    {
        if ($this->uploaded_by_type === 'employee') {
            return $this->belongsTo(Employee::class, 'uploaded_by_id', 'employee_id');
        } elseif ($this->uploaded_by_type === 'customer') {
            return $this->belongsTo(Customer::class, 'uploaded_by_id', 'customer_id');
        }
        return null;
    }

    public function scopeByType($query, $type)
    {
        return $query->where('attachment_type', $type);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TicketAttachment extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Ticket';

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
     * 2. File lokal (file_path tersedia) → route /attachments/{id} juga
     * 3. Fallback → link_url dari DB
     *
     * File lokal sengaja TIDAK memakai '/storage/' . $file_path: nama file di disk
     * adalah hash acak dari UploadedFile::store(), dan route bawaan Laravel
     * (public disk `serve => true`) mengirim `Content-Disposition: inline;
     * filename="<hash>.docx"`. Filename di header itu menang atas atribut
     * `download="..."` di <a>, jadi browser menyimpan file bernama acak. Proxy
     * /attachments/{id} mengirim file_name asli dari DB di header tersebut.
     */
    public function getPublicUrlAttribute(): ?string
    {
        // Record baru: file tidak disimpan lokal, ambil dari Graph via proxy
        if ($this->graph_message_id && $this->graph_attachment_id) {
            return route('attachments.show', $this->id);
        }

        // File lokal (internal note, ticket non-email) — di-stream via proxy yang sama
        if ($this->file_path) {
            return route('attachments.show', $this->id);
        }

        // Berkas yang dibagikan di group chat Teams: tautannya menunjuk langsung
        // ke SharePoint, dan SharePoint meminta login Microsoft lebih dulu.
        // Dilewatkan proxy yang sama dengan lampiran email supaya perilakunya
        // seragam bagi orang yang membuka tiket.
        if ($this->isCloudProxyable()) {
            return route('attachments.show', $this->id);
        }

        return $this->link_url;
    }

    /**
     * Tautan cloud yang boleh diambilkan EcoSystem lewat Graph.
     *
     * Host-nya dibatasi dengan SENGAJA. `link_url` berasal dari lampiran pesan
     * Teams — isinya ditentukan orang lain, dan proxy ini mengambil berkas
     * memakai token aplikasi yang punya akses luas. Tanpa pembatasan host, satu
     * baris attachment berisi URL sembarang bisa memancing server mengambil
     * apa pun yang bisa dijangkau token itu.
     */
    public function isCloudProxyable(): bool
    {
        if ($this->attachment_type !== 'link' || !$this->link_url) {
            return false;
        }

        $host = strtolower((string) parse_url($this->link_url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        foreach (['sharepoint.com', 'onedrive.com', 'onedrive.live.com', '1drv.ms'] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
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

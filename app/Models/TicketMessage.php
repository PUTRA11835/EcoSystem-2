<?php

namespace App\Models;

use App\Services\InlineImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    use HasFactory;

    protected $table = 'ticket_message';

    /**
     * Metadata gambar inline yang baru disimpan ke disk pada event `saving`,
     * menunggu dibuatkan baris TicketAttachment di event `saved` (butuh id row).
     * Bukan atribut DB — tidak masuk $fillable/$casts.
     *
     * @var array<int, array{file_path:string, file_name:string, file_size:int, mime_type:string}>
     */
    protected array $pendingInlineImages = [];

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'sender_id',
        'sender_email',
        'sender_name',
        'message',
        'message_html',
        'sla_message',
        'sla_message_by',
        'sla_message_at',
        'is_internal_note',
        'is_deleted',
        'edited_at',
        'message_type',
        'reply_to_id',
        'channel',
        'email_message_id',
        'email_status',
        'email_error',
        'email_recipients',
        'email_failed_recipients',
        'email_in_reply_to',
        'cc_emails',
        'is_read_by_customer',
        'is_read_by_agent',
        'read_at',
        'mentioned_employee_ids',
        'mentioned_role_ids',
    ];

    protected $casts = [
        'is_internal_note'       => 'boolean',
        'is_deleted'             => 'boolean',
        'edited_at'              => 'datetime',
        'sla_message_at'         => 'datetime',
        'is_read_by_customer'    => 'boolean',
        'is_read_by_agent'       => 'boolean',
        'read_at'                => 'datetime',
        'cc_emails'              => 'array',
        'email_recipients'        => 'array',
        'email_failed_recipients' => 'array',
        'mentioned_employee_ids' => 'array',
        'mentioned_role_ids'     => 'array',
    ];

    /**
     * Choke point invariant "byte gambar tidak pernah di DB".
     *
     * Sebelum row disimpan, setiap `<img src="data:image/...;base64,...">` di
     * message_html diekstrak ke public disk dan diganti URL /storage/... Berlaku
     * untuk SEMUA penulis message_html (email masuk, balasan agent, internal note,
     * approval staging, deliverable, dll) tanpa perlu menyentuh tiap controller.
     * Baris metadata TicketAttachment dibuat pada `saved` karena butuh id message.
     */
    protected static function booted(): void
    {
        static::saving(function (TicketMessage $m) {
            if (!$m->ticket_id || !$m->message_html) {
                return;
            }
            // Hanya proses saat message_html berubah & memang mengandung data URI.
            if (!$m->isDirty('message_html') || stripos($m->message_html, 'data:image') === false) {
                return;
            }

            $pending = [];
            $m->message_html = app(InlineImageService::class)->persistBase64ToStorage(
                $m->message_html,
                (string) $m->ticket_id,
                function (array $meta) use (&$pending) {
                    $pending[] = $meta;
                }
            );
            $m->pendingInlineImages = $pending;
        });

        static::saved(function (TicketMessage $m) {
            if (empty($m->pendingInlineImages)) {
                return;
            }

            foreach ($m->pendingInlineImages as $meta) {
                TicketAttachment::create([
                    'ticket_id'        => $m->ticket_id,
                    'message_id'       => $m->id,
                    'uploaded_by_type' => 'system',
                    'uploaded_by_id'   => null,
                    'attachment_type'  => 'image',
                    'file_name'        => $meta['file_name'],
                    'file_size'        => $meta['file_size'],
                    'mime_type'        => $meta['mime_type'],
                    'is_inline'        => true,
                    'file_path'        => $meta['file_path'],
                ]);
            }

            $m->pendingInlineImages = [];
        });
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function sender()
    {
        if ($this->sender_type === 'employee') {
            return $this->belongsTo(Employee::class, 'sender_id', 'employee_id');
        } elseif ($this->sender_type === 'customer') {
            return $this->belongsTo(Customer::class, 'sender_id', 'customer_id');
        }
        return null;
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'message_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(TicketMessage::class, 'reply_to_id');
    }

    public function slaMessageBy()
    {
        return $this->belongsTo(Employee::class, 'sla_message_by', 'employee_id');
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal_note', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal_note', false);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeUnreadByCustomer($query)
    {
        return $query->where('is_read_by_customer', false);
    }

    public function scopeUnreadByAgent($query)
    {
        return $query->where('is_read_by_agent', false);
    }
}

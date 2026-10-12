<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu internal note yang menunggu dikirim ke group chat Teams.
 *
 * Lihat docs/teams-chat-sync-design.md §5 dan §8.
 */
class TeamsOutboxItem extends Model
{
    protected $table = 'teams_outbox';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'ticket_id',
        'chat_id',
        'ticket_message_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'sent_at',
    ];

    /** Disamakan dengan default kolom di migrasi — lihat TicketTeamsChat. */
    protected $attributes = [
        'status'   => self::STATUS_PENDING,
        'attempts' => 0,
    ];

    protected $casts = [
        'payload'  => 'array',
        'attempts' => 'integer',
        'sent_at'  => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function markSent(): void
    {
        $this->status     = self::STATUS_SENT;
        $this->sent_at    = now();
        $this->last_error = null;
        $this->save();
    }

    /**
     * Gagal kirim. Selama percobaan masih tersisa, baris dikembalikan ke pending
     * supaya putaran berikutnya mencobanya lagi; sesudah itu ia BERHENTI di
     * 'failed' dengan pesan errornya tersimpan — terlihat sebagai data, bukan
     * hilang jadi baris log.
     */
    public function markAttemptFailed(string $error, int $maxAttempts): void
    {
        $this->attempts++;
        $this->last_error = mb_substr($error, 0, 2000);
        $this->status     = $this->attempts >= $maxAttempts
            ? self::STATUS_FAILED
            : self::STATUS_PENDING;
        $this->save();
    }
}

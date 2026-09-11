<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu giliran dalam percakapan AI, disimpan sebagai teks Markdown.
 *
 * 'attachment_count' bukan pointer ke berkas: lampiran memang tidak diarsipkan,
 * angkanya hanya untuk menjelaskan transkrip lama ("ada 1 gambar di sini").
 */
class AiMessage extends Model
{
    protected $table = 'ai_messages';

    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'sources',
        'attachment_count',
    ];

    protected $casts = [
        'sources' => 'array',
        'attachment_count' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu percakapan AI milik satu employee.
 *
 * Arsip yang bisa dibaca manusia — BUKAN konteks yang dikirim ke API. Isinya
 * teks saja; lampiran dan blok server tool tidak pernah sampai ke sini
 * (lihat migrasi create_ai_conversation_tables).
 */
class AiConversation extends Model
{
    public const ASSISTANT_RESEARCH = 'research';
    public const ASSISTANT_INTERNAL = 'internal';

    protected $table = 'ai_conversations';

    protected $fillable = [
        'employee_id',
        'assistant',
        'conversation_id',
        'title',
        'model_tier',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'ai_conversation_id')->orderBy('id');
    }

    /**
     * Judul dari pesan pertama user — dipotong di batas kata.
     *
     * Sengaja TIDAK memanggil API hanya untuk meringkas judul: itu menambah
     * biaya dan latensi pada tiap percakapan baru, sementara 60 karakter
     * pertama pertanyaan sudah cukup untuk mengenalinya di daftar.
     */
    public static function titleFrom(string $text, int $attachmentCount = 0): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ('' === $clean) {
            return $attachmentCount > 0 ? 'Lampiran tanpa pertanyaan' : 'Percakapan baru';
        }

        if (mb_strlen($clean) <= 60) {
            return $clean;
        }

        $cut = mb_substr($clean, 0, 60);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > 30 ? mb_substr($cut, 0, $space) : $cut) . '…';
    }
}

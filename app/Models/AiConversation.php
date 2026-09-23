<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu percakapan AI. Dua bentuk, dibedakan lewat ticket_id:
 *   - ticket_id NULL     → percakapan PRIVAT milik satu employee (dibuat
 *     saat tombol "Ask AI Research" diklik) — employee_id adalah pemiliknya.
 *   - ticket_id TERISI   → room BERSAMA satu tiket (dibuat otomatis saat
 *     StagingTicketController::approve()), boleh dibaca+ditulis semua
 *     anggota tim tiket (lihat TicketTeamAccess::canAccessAiResearch()).
 *     employee_id di sini cuma metadata "siapa yang approve", BUKAN
 *     penentu akses.
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
        'ticket_id',
        'assistant',
        'conversation_id',
        'title',
        'model_tier',
        'last_message_at',
        'initial_reply_claimed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'initial_reply_claimed_at' => 'datetime',
    ];

    /**
     * Klaim atomic "aku yang memicu jawaban pembuka" — cuma relevan untuk
     * room BERSAMA (ticket_id terisi), di mana lebih dari satu anggota tim
     * bisa membuka room yang sama nyaris bersamaan begitu tiket di-approve.
     * Tanpa ini, dua orang yang buka bareng bisa sama-sama memicu panggilan
     * AI untuk seed yang sama — dobel biaya, dan dua jawaban pembuka yang
     * berbeda mendarat di satu thread bersama.
     *
     * UPDATE ber-syarat (bukan lock aplikasi) — pola sama persis dengan
     * ai_analysis_status di StagingTicket. Klaim yang lebih tua dari
     * $staleMinutes boleh diklaim ulang: request yang mati di tengah jalan
     * (browser ditutup, timeout) sebelum sempat mengarsipkan jawaban tidak
     * boleh mengunci room ini selamanya.
     *
     * @return bool true kalau BARIS INI yang berhasil klaim (boleh lanjut
     *              memanggil AI); false kalau sudah diklaim baris lain
     *              (proses lain sedang/sudah menjawab — jangan panggil AI lagi).
     */
    public function claimInitialReply(int $staleMinutes = 15): bool
    {
        $claimed = static::where('id', $this->id)
            ->where(function ($query) use ($staleMinutes) {
                $query->whereNull('initial_reply_claimed_at')
                    ->orWhere('initial_reply_claimed_at', '<', now()->subMinutes($staleMinutes));
            })
            ->update(['initial_reply_claimed_at' => now()]);

        return $claimed > 0;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'ai_conversation_id')->orderBy('id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
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

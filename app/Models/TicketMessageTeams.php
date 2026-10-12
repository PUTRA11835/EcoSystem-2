<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jembatan satu ticket_message <-> satu pesan Teams, sekaligus kunci anti-loop.
 *
 * Lihat docs/teams-chat-sync-design.md §5.
 */
class TicketMessageTeams extends Model
{
    protected $table = 'ticket_message_teams';

    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    protected $fillable = [
        'ticket_message_id',
        'chat_id',
        'teams_message_id',
        'direction',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    /**
     * Id pesan Teams yang SUDAH tercatat untuk sebuah chat.
     *
     * Dipakai polling untuk membuang pesan yang sudah diserap — termasuk pesan
     * yang kita sendiri yang kirim, yang tanpa pagar ini akan diserap balik jadi
     * internal note baru dan memicu loop.
     *
     * @param  string[] $teamsMessageIds
     * @return string[] yang sudah ada
     */
    public static function existingIds(string $chatId, array $teamsMessageIds): array
    {
        if ($teamsMessageIds === []) {
            return [];
        }

        return static::query()
            ->where('chat_id', $chatId)
            ->whereIn('teams_message_id', $teamsMessageIds)
            ->pluck('teams_message_id')
            ->all();
    }
}

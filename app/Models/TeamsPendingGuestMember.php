<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Guest yang menunggu dimasukkan ke group chat tiket, karena Teams belum
 * mengenalnya saat chat dibuat. Lihat migrasi teams_pending_guest_member dan
 * App\Console\Commands\AddPendingTeamsGuests.
 */
class TeamsPendingGuestMember extends Model
{
    protected $table = 'teams_pending_guest_member';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ADDED   = 'added';
    public const STATUS_FAILED  = 'failed';

    /**
     * Jeda sebelum percobaan berikutnya, dalam menit, menurut jumlah percobaan
     * yang sudah gagal. Indeks 0 = jeda sebelum percobaan pertama.
     *
     * Awalnya rapat karena propagasi guest biasanya selesai dalam hitungan menit
     * (26 Okt 2026: gagal di detik ke-2, berhasil di menit ke-12), lalu
     * merenggang supaya gangguan yang lebih lama tetap tertangkap tanpa
     * memanggil Graph tiap menit.
     */
    public const BACKOFF_MINUTES = [2, 3, 5, 10, 15, 30, 60, 120, 240, 480];

    protected $fillable = [
        'ticket_id',
        'email',
        'status',
        'attempts',
        'next_attempt_at',
        'last_error',
        'added_at',
        'flow_payload',
    ];

    /** Disamakan dengan default kolom di migrasi — lihat TicketTeamsChat. */
    protected $attributes = [
        'status'   => self::STATUS_PENDING,
        'attempts' => 0,
    ];

    protected $casts = [
        'attempts'        => 'integer',
        'next_attempt_at' => 'datetime',
        'added_at'        => 'datetime',
        'flow_payload'    => 'array',
    ];

    public static function backoffMinutes(int $failedAttempts): int
    {
        $steps = self::BACKOFF_MINUTES;

        return $steps[min(max($failedAttempts, 0), count($steps) - 1)];
    }

    public function scopeDue(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING)
            ->where('next_attempt_at', '<=', now());
    }

    public function markAdded(): void
    {
        $this->status     = self::STATUS_ADDED;
        $this->added_at   = now();
        $this->last_error = null;
        $this->save();
    }

    /**
     * Gagal menambahkan. Selama percobaan masih tersisa, baris tetap pending
     * dengan jadwal berikutnya digeser menurut BACKOFF_MINUTES; sesudah itu ia
     * BERHENTI di 'failed' dengan pesan errornya tersimpan.
     */
    public function markAttemptFailed(string $error, int $maxAttempts): void
    {
        $this->attempts++;
        $this->last_error      = mb_substr($error, 0, 2000);
        $this->status          = $this->attempts >= $maxAttempts ? self::STATUS_FAILED : self::STATUS_PENDING;
        $this->next_attempt_at = now()->addMinutes(self::backoffMinutes($this->attempts));
        $this->save();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Group chat Teams milik sebuah tiket + kursor polling-nya.
 *
 * Sengaja TIDAK memakai trait Auditable: barisnya ditulis oleh command penjadwal
 * tiap menit (last_polled_at, poll_failures), jadi audit log-nya akan jadi derau
 * murni tanpa ada manusia yang bisa dipertanggungjawabkan.
 *
 * Lihat docs/teams-chat-sync-design.md §5.
 */
class TicketTeamsChat extends Model
{
    protected $table = 'ticket_teams_chat';

    protected $fillable = [
        'ticket_id',
        'chat_id',
        'topic',
        'created_via',
        'sync_enabled',
        'last_message_at',
        'last_polled_at',
        'poll_failures',
    ];

    /**
     * Disamakan dengan default kolom di migrasi.
     *
     * Tanpa ini, instance hasil create() memegang NULL untuk kolom-kolom itu
     * sampai di-fresh() — sehingga `if ($chat->sync_enabled)` tepat sesudah chat
     * dibuat akan bernilai salah, padahal barisnya di DB sudah benar.
     */
    protected $attributes = [
        'created_via'   => 'graph',
        'sync_enabled'  => true,
        'poll_failures' => 0,
    ];

    protected $casts = [
        'sync_enabled'    => 'boolean',
        'last_message_at' => 'datetime',
        'last_polled_at'  => 'datetime',
        'poll_failures'   => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessageTeams::class, 'chat_id', 'chat_id');
    }

    /**
     * Kursor $filter untuk Graph. Null berarti baris ini belum pernah dipoll
     * dan pemanggilnya harus menetapkan batas bawah sendiri — JANGAN diperlakukan
     * sebagai "tarik semua", karena itu akan menyeret seluruh riwayat chat masuk
     * ke thread tiket (design §7).
     */
    public function cursorIso(): ?string
    {
        return $this->last_message_at?->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Polling berhasil: majukan kursor kalau ada pesan yang lebih baru, dan reset
     * hitungan kegagalan.
     */
    public function markPolled(?\DateTimeInterface $newest = null): void
    {
        $this->last_polled_at = now();
        $this->poll_failures  = 0;

        if ($newest) {
            // WAJIB dikonversi ke zona waktu aplikasi sebelum disimpan.
            //
            // Graph selalu mengirim UTC, sedangkan kolom datetime ini dibaca
            // Laravel sebagai Asia/Jakarta. Menyimpan jam-dinding UTC apa adanya
            // membuat cursorIso() — yang mengonversinya balik ke UTC —
            // menghasilkan nilai 7 jam terlalu awal. Akibatnya jendela polling
            // selamanya 7 jam lebih lebar: hasilnya tetap benar karena ada
            // dedupe, tapi tiap menit memindai ulang pesan yang sudah lama
            // diserap dan pagar max_per_poll bisa habis olehnya.
            $newest = Carbon::instance(
                $newest instanceof \DateTimeImmutable
                    ? \DateTime::createFromImmutable($newest)
                    : $newest
            )->setTimezone(config('app.timezone'));

            // Hanya maju, tidak pernah mundur — pesan lama yang termodifikasi
            // (mis. gambar yang selesai diproses Teams) tidak boleh menarik
            // kursor ke belakang dan membuat pesan sesudahnya diserap ulang.
            if (!$this->last_message_at || $newest->gt($this->last_message_at)) {
                $this->last_message_at = $newest;
            }
        }

        $this->save();
    }

    /**
     * Polling gagal. Melewati ambang, sinkron chat ini dimatikan sendiri supaya
     * chat yang chat_id-nya sudah tidak valid tidak dipanggil selamanya.
     *
     * @return bool true kalau panggilan ini yang mematikannya.
     */
    public function markPollFailed(int $threshold): bool
    {
        $this->last_polled_at = now();
        $this->poll_failures++;

        $disabled = false;

        if ($threshold > 0 && $this->poll_failures >= $threshold && $this->sync_enabled) {
            $this->sync_enabled = false;
            $disabled = true;
        }

        $this->save();

        return $disabled;
    }
}

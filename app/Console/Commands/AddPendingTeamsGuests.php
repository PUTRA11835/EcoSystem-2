<?php

namespace App\Console\Commands;

use App\Models\TeamsPendingGuestMember;
use App\Services\PowerAutomateService;
use App\Services\Teams\TeamsChatService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Masukkan consultant eksternal (guest) yang tertunda ke group chat tiketnya.
 *
 * Guest yang baru diundang belum dikenal Teams selama beberapa menit, jadi
 * TeamsChatService membuat chat tanpa dia lalu mengantrenya di
 * `teams_pending_guest_member`. Command ini mencobanya lagi dengan jeda yang
 * merenggang, lalu — kalau barisnya membawa payload flow 6 — mengirim pesan
 * "ditambahkan ke tiket" begitu orangnya benar-benar sudah anggota.
 *
 * Dijadwalkan tiap menit. Baris yang tidak kunjung berhasil berhenti di
 * `failed` dengan `last_error` terisi; --retry-failed mengembalikannya.
 */
class AddPendingTeamsGuests extends Command
{
    protected $signature = 'teams:add-pending-guests
                            {--retry-failed : Kembalikan baris berstatus failed ke pending lalu coba lagi}
                            {--dry-run : Tampilkan antrean tanpa memanggil Graph}';

    protected $description = 'Masukkan guest Teams yang tertunda ke group chat tiketnya';

    public function handle(TeamsChatService $chats, PowerAutomateService $powerAutomate): int
    {
        if ($this->option('retry-failed')) {
            $n = TeamsPendingGuestMember::where('status', TeamsPendingGuestMember::STATUS_FAILED)
                ->update([
                    'status'          => TeamsPendingGuestMember::STATUS_PENDING,
                    'attempts'        => 0,
                    'next_attempt_at' => now(),
                ]);

            $this->info("{$n} baris failed dikembalikan ke pending.");
        }

        if (!$chats->isEnabled()) {
            $this->line('Sinkron Teams tidak aktif — dilewati.');

            return self::SUCCESS;
        }

        $rows = TeamsPendingGuestMember::due()->orderBy('next_attempt_at')->limit(25)->get();

        if ($rows->isEmpty()) {
            $this->line('Antrean kosong.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['id', 'ticket', 'email', 'attempts', 'next', 'error'],
                $rows->map(fn (TeamsPendingGuestMember $r) => [
                    $r->id,
                    $r->ticket_id,
                    $r->email,
                    $r->attempts,
                    $r->next_attempt_at,
                    mb_substr((string) $r->last_error, 0, 40),
                ])->all()
            );

            return self::SUCCESS;
        }

        $added = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if (!$chats->addPendingGuest($row)) {
                $failed++;

                if ($row->status === TeamsPendingGuestMember::STATUS_FAILED) {
                    $this->error("  {$row->email} (tiket {$row->ticket_id}) MENYERAH setelah {$row->attempts} percobaan");
                }

                continue;
            }

            $added++;

            if ($row->flow_payload) {
                try {
                    $powerAutomate->dispatch(PowerAutomateService::FLOW_TICKET_MEMBER_ADDED, $row->flow_payload);
                } catch (\Throwable $e) {
                    Log::warning('teams:add-pending-guests: flow 6 gagal dikirim (non-fatal)', [
                        'ticket_id' => $row->ticket_id,
                        'email'     => $row->email,
                        'error'     => mb_substr($e->getMessage(), 0, 500),
                    ]);
                }
            }
        }

        $this->info("Selesai: {$added} masuk grup, {$failed} belum dari {$rows->count()} baris.");

        return self::SUCCESS;
    }
}

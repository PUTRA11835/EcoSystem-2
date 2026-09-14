<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketOpenReminder;
use App\Services\PowerAutomateService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reminder berulang ke lead modul selama tiket masih berstatus `open`.
 *
 * Command ini TIDAK menyimpan daftar tiket yang perlu diingatkan di mana pun —
 * ia menghitung ulang tiap kali jalan dari status tiket saat ini. Karena itu
 * "berhenti kalau status bukan open lagi" tidak butuh kode pembatal: begitu
 * helpdesk memindahkan status, tiketnya hilang sendiri dari hasil query.
 *
 * Yang disimpan hanya jejak pengiriman (tabel ticket_open_reminders) supaya
 * jarak antar reminder terjaga dan run yang tumpang tindih tidak mengirim dua
 * kali untuk tiket yang sama.
 *
 * Pengiriman yang GAGAL sengaja tidak memperbarui last_sent_at, jadi tiket itu
 * otomatis dicoba lagi pada run berikutnya.
 */
class SendOpenTicketReminders extends Command
{
    protected $signature = 'tickets:open-reminders
                            {--dry-run : Tampilkan tiket yang akan dikirimi reminder tanpa memanggil Power Automate}
                            {--limit= : Override jumlah tiket maksimum per run (default dari config)}';

    protected $description = 'Kirim reminder tiket berstatus open ke lead modul lewat Power Automate (Microsoft Teams)';

    public function handle(PowerAutomateService $powerAutomate): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && !$powerAutomate->isFlowReady(PowerAutomateService::FLOW_TICKET_OPEN_REMINDER)) {
            // Bukan error: begini caranya fitur ini dimatikan (POWER_AUTOMATE_ENABLED=false
            // atau URL flow dikosongkan). Scheduler tetap jalan tiap menit tanpa efek.
            $this->line('Flow ticket_open_reminder tidak aktif — tidak ada yang dikirim.');
            return self::SUCCESS;
        }

        $config           = (array) config('services.power_automate.reminder', []);
        $intervalMinutes  = max(1, (int) ($config['interval_minutes'] ?? 1));
        $maxCount         = max(0, (int) ($config['max_count'] ?? 0));
        $batchLimit       = (int) ($this->option('limit') ?? $config['batch_limit'] ?? 50);
        $stopWhenAssigned = (bool) ($config['stop_when_assigned'] ?? false);
        $since            = $this->parseSince($config['since'] ?? null);

        $tickets = $this->dueTickets($intervalMinutes, $maxCount, max(1, $batchLimit), $since);

        if ($tickets->isEmpty()) {
            $this->line('Tidak ada tiket open yang jatuh tempo reminder.');
            return self::SUCCESS;
        }

        $sent = $skippedNoLead = $skippedAssigned = $failed = 0;

        foreach ($tickets as $ticket) {
            $payloadTicket = $powerAutomate->ticketPayload($ticket);

            if ($stopWhenAssigned && $payloadTicket['is_assigned']) {
                $skippedAssigned++;
                continue;
            }

            $leads      = $powerAutomate->moduleLeads($payloadTicket['module_id'], $payloadTicket['module']);
            $leadEmails = $powerAutomate->leadEmails($leads);

            if ($leadEmails === []) {
                // Modul belum punya lead, atau lead-nya belum punya email kerja.
                // Sengaja TIDAK mencatat pengiriman: begitu lead dilengkapi di
                // Master Module, reminder langsung jalan tanpa perlu intervensi.
                $skippedNoLead++;
                Log::debug('tickets:open-reminders: tiket dilewati, tidak ada lead modul dengan email kerja', [
                    'ticket_id' => $payloadTicket['id'],
                    'module'    => $payloadTicket['module'],
                    'module_id' => $payloadTicket['module_id'],
                ]);
                continue;
            }

            $previousCount = (int) ($ticket->getAttribute('sent_count') ?? 0);

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] #%s (%s) open %d menit -> %s (reminder ke-%d)',
                    $payloadTicket['number'],
                    $payloadTicket['module'] ?: 'tanpa modul',
                    $payloadTicket['age_minutes'],
                    implode(', ', $leadEmails),
                    $previousCount + 1
                ));
                $sent++;
                continue;
            }

            $ok = $powerAutomate->dispatch(PowerAutomateService::FLOW_TICKET_OPEN_REMINDER, [
                'ticket'       => $payloadTicket,
                'module_leads' => $leads,
                'lead_emails'  => $leadEmails,
                'reminder'     => [
                    'count'            => $previousCount + 1,
                    'interval_minutes' => $intervalMinutes,
                    'open_for_minutes' => $payloadTicket['age_minutes'],
                    'is_first'         => $previousCount === 0,
                ],
            ]);

            if (!$ok) {
                $failed++;
                continue;
            }

            $this->recordSent((int) $payloadTicket['id']);
            $sent++;
        }

        $this->info(sprintf(
            'Reminder tiket open: %d terkirim, %d dilewati (tanpa lead), %d dilewati (sudah di-assign), %d gagal.',
            $sent,
            $skippedNoLead,
            $skippedAssigned,
            $failed
        ));

        return self::SUCCESS;
    }

    /**
     * Tiket open yang sudah waktunya diingatkan lagi.
     *
     * Belum pernah dikirimi reminder didahulukan, lalu yang paling lama tidak
     * dikirimi — supaya batas batch memotong antrean secara adil, bukan selalu
     * mengunci tiket yang sama.
     */
    private function dueTickets(int $intervalMinutes, int $maxCount, int $batchLimit, ?Carbon $since)
    {
        $cutoff = now()->subMinutes($intervalMinutes);

        return Ticket::query()
            ->leftJoin('ticket_open_reminders as r', 'r.ticket_id', '=', 'ticket.ticket_id')
            ->where('ticket.status', 'open')
            // Tiket yang disembunyikan dari daftar juga tidak layak diingatkan.
            // Baris lama bisa punya is_hidden NULL, jadi NULL diperlakukan sebagai false.
            ->where(function ($q) {
                $q->whereNull('ticket.is_hidden')->orWhere('ticket.is_hidden', false);
            })
            ->when($since, fn ($q) => $q->where('ticket.created_at', '>=', $since))
            ->when($maxCount > 0, function ($q) use ($maxCount) {
                $q->where(function ($w) use ($maxCount) {
                    $w->whereNull('r.sent_count')->orWhere('r.sent_count', '<', $maxCount);
                });
            })
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('r.last_sent_at')->orWhere('r.last_sent_at', '<=', $cutoff);
            })
            ->orderByRaw('r.last_sent_at IS NULL DESC')
            ->orderBy('r.last_sent_at')
            ->limit($batchLimit)
            ->select('ticket.*', 'r.sent_count')
            ->get();
    }

    private function recordSent(int $ticketId): void
    {
        $state = TicketOpenReminder::firstOrNew(['ticket_id' => $ticketId]);

        $state->sent_count    = (int) $state->sent_count + 1;
        $state->first_sent_at = $state->first_sent_at ?? now();
        $state->last_sent_at  = now();
        $state->save();
    }

    /**
     * POWER_AUTOMATE_REMINDER_SINCE membatasi reminder ke tiket yang dibuat sejak
     * fitur dinyalakan. Nilai yang tidak bisa dibaca diabaikan (dengan peringatan)
     * daripada mematikan seluruh reminder secara diam-diam.
     */
    private function parseSince($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            $this->warn("POWER_AUTOMATE_REMINDER_SINCE tidak valid ('{$value}') — batas waktu diabaikan.");
            return null;
        }
    }
}

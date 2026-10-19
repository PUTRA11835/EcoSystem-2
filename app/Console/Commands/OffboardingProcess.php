<?php

namespace App\Console\Commands;

use App\Services\Offboarding\OffboardingService;
use Illuminate\Console\Command;

/**
 * Harian: mengingatkan tugas Offboarding yang segera/sudah jatuh tempo lewat bel notifikasi, dan — bila saklar "Deactivate access automatically"
 * di Offboarding Settings menyala — menonaktifkan akses pada tanggal efektif. Saklar modul mati → tidak berbuat apa-apa.
 * `--dry-run` hanya menghitung.
 */
class OffboardingProcess extends Command
{
    protected $signature = 'offboarding:process {--dry-run : Count what would happen, change nothing}';

    protected $description = 'Remind HR of offboarding tasks and deactivate access on the effective date (when switched on)';

    public function handle(OffboardingService $service): int
    {
        $r = $service->processDaily((bool) $this->option('dry-run'));

        $this->info("Reminders: {$r['reminded']} · Access deactivated: {$r['deactivated']}" . ($this->option('dry-run') ? ' (dry run)' : ''));
        foreach ($r['skipped'] as $why) {
            $this->line('  - ' . $why);
        }

        return self::SUCCESS;
    }
}

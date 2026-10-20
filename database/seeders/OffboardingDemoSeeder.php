<?php

namespace Database\Seeders;

use App\Services\Offboarding\OffboardingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data uji Offboarding (HR & General → Offboarding): tiga proses berjalan dengan checklist yang sebagian selesai.
 *
 *   DEMO-INT02  Indra — Resignation, efektif 30 hari lagi (PKWTT)
 *   DEMO-INT01  Intan — End of PKWT, efektif 10 hari lagi (PKWT) · dua tugas selesai
 *   DEMO-EXT04  Nara  — End of engagement (konsultan), efektif 20 hari lagi · satu tugas selesai
 *
 *   php artisan db:seed --class=OffboardingDemoSeeder
 *   OFFBOARDING_DEMO_CLEAN=1 php artisan db:seed --class=OffboardingDemoSeeder   # hapus HANYA Offboarding bertanda seeder ini
 *
 * Penanda: kolom `notes` "Seeder demo (Offboarding)". Seeder TIDAK menonaktifkan siapa pun dan TIDAK mengubah pengaturan: saklar modul dinyalakan
 * sebentar hanya agar layanan mau membuat data, lalu dikembalikan persis seperti semula (bawaan: MATI). Kalkulator tetap mati sampai aturan
 * hukumnya diulas di Legal Basis. Menolak production. Butuh akun DEMO-* (jalankan ContractDemoSeeder / ExternalDemoSeeder dulu).
 */
class OffboardingDemoSeeder extends Seeder
{
    private const MARK = 'Seeder demo (Offboarding)';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->say('Dihentikan: data uji Offboarding tidak boleh dibuat di production.');

            return;
        }

        if (env('OFFBOARDING_DEMO_CLEAN')) {
            $n = DB::table('offboardings')->where('notes', 'like', self::MARK . '%')->delete();   // tugas & settlement ikut terhapus (cascade)
            $this->say("Dibersihkan: {$n} offboarding demo.");

            return;
        }

        $ids = DB::table('employee')->whereIn('eci', ['DEMO-INT01', 'DEMO-INT02', 'DEMO-EXT04'])->pluck('employee_id', 'eci')->all();
        if (count($ids) < 3) {
            $this->say('Akun DEMO-INT01/02 atau DEMO-EXT04 belum ada — jalankan ContractDemoSeeder dan ExternalDemoSeeder dulu.');

            return;
        }

        $service = app(OffboardingService::class);
        $before = DB::table('offboarding_settings')->orderBy('id')->first();
        $settingsId = $before?->id;
        DB::table('offboarding_settings')->where('id', $settingsId)->update(['module_enabled' => 1, 'include_external' => 1]);

        try {
            DB::table('offboardings')->where('notes', 'like', self::MARK . '%')->delete();
            $today = now();
            $make = function (string $eci, string $type, int $days, ?string $reason = null) use ($service, $ids, $today): int {
                // Offboarding terbuka milik orang ini (mis. buatan Anda) dibiarkan; seeder hanya membuat bila belum ada.
                if (DB::table('offboardings')->where('employee_id', $ids[$eci])->where('status', 'in_progress')->exists()) {
                    $this->say("{$eci}: sudah punya offboarding berjalan — dilewati.");

                    return 0;
                }

                return $service->create([
                    'employee_id' => $ids[$eci], 'offboarding_type' => $type, 'reason_code' => $reason, 'effective_date' => $today->copy()->addDays($days)->toDateString(),
                    'last_working_day' => $today->copy()->addDays($days - 1)->toDateString(), 'notes' => self::MARK . ' — jangan dipakai sebagai proses sungguhan.',
                ], 1);
            };
            $done = function (int $offboardingId, array $keys) {
                if ($offboardingId === 0) {
                    return;
                }
                DB::table('offboarding_tasks')->where('offboarding_id', $offboardingId)->whereIn('task_key', $keys)
                    ->update(['status' => 'done', 'completed_at' => now(), 'completed_by' => 1, 'note' => 'Done (demo)', 'updated_at' => now()]);
            };

            $indra = $make('DEMO-INT02', 'resignation', 30);
            $intan = $make('DEMO-INT01', 'end_of_pkwt', 10);
            $nara = $make('DEMO-EXT04', 'end_of_engagement', 20);
            $done($intan, ['validate_basics', 'exit_interview']);
            $done($nara, ['validate_basics']);
            $done($indra, []);
        } finally {
            if ($before) {
                DB::table('offboarding_settings')->where('id', $settingsId)->update([
                    'module_enabled' => $before->module_enabled, 'include_external' => $before->include_external, 'updated_at' => now(),
                ]);
            }
        }

        $this->say('Selesai. Saklar modul dikembalikan seperti semula (' . ($before && $before->module_enabled ? 'MENYALA' : 'MATI — nyalakan di Offboarding → Settings untuk bekerja dengannya') . ').');
        $this->say('Bersihkan: OFFBOARDING_DEMO_CLEAN=1 php artisan db:seed --class=OffboardingDemoSeeder');
    }

    private function say(string $m): void
    {
        $this->command ? $this->command->info($m) : print($m . "\n");
    }
}

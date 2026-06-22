<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill employee_address.email_work dari auth_users.email untuk data lama.
 *
 * Latar: email login (sumber set/reset password) tersimpan di auth_users.email,
 * tetapi field "Email (Work)" di UI membaca employee_address.email_work. Import
 * lama hanya mengisi auth_users.email sehingga email tak terlihat di UI. Command
 * ini menyalin email login ke email_work alamat PRIMARY untuk employee lama.
 *
 * Aman & idempotent:
 * - Hanya mengisi email_work yang masih KOSONG (tidak menimpa isian manual).
 * - Tidak menyentuh password atau field lain.
 * - Bisa dijalankan berulang tanpa efek ganda.
 */
class BackfillEmployeeEmailWork extends Command
{
    protected $signature = 'employees:backfill-email-work
                            {--dry-run : Tampilkan perubahan tanpa menyimpan}
                            {--overwrite : Timpa email_work yang sudah terisi (default: hanya isi yang kosong)}';

    protected $description = 'Salin auth_users.email ke employee_address.email_work (alamat primary) untuk data employee lama';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        if ($dryRun) {
            $this->warn('Mode DRY-RUN — tidak ada perubahan yang disimpan.');
        }

        // Akun employee dengan email login terisi (customer dilewati).
        $authUsers = DB::table('auth_users')
            ->whereNotNull('employee_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select('employee_id', 'email')
            ->get();

        if ($authUsers->isEmpty()) {
            $this->info('Tidak ada akun employee dengan email login. Tidak ada yang di-backfill.');
            return 0;
        }

        $this->info("Memproses {$authUsers->count()} akun employee...");

        $filled   = 0; // email_work kosong → diisi
        $created   = 0; // employee tanpa alamat → alamat primary baru dibuat
        $updated   = 0; // email_work ditimpa (--overwrite)
        $skipped   = 0; // sudah terisi & tidak --overwrite
        $unchanged = 0; // email_work sudah sama dengan email login

        foreach ($authUsers as $au) {
            $email = trim((string) $au->email);

            $primary = DB::table('employee_address')
                ->where('employee_id', $au->employee_id)
                ->orderBy('is_primary', 'desc')
                ->orderBy('address_id', 'asc')
                ->first();

            if (!$primary) {
                // Belum punya alamat → buat alamat primary minimal (pola upsertEmailWork).
                if ($dryRun) {
                    $this->line("  [buat]   emp #{$au->employee_id}: alamat primary baru, email_work = {$email}");
                } else {
                    DB::table('employee_address')->insert([
                        'employee_id'  => $au->employee_id,
                        'address_type' => 'Home',
                        'email_work'   => $email,
                        'is_primary'   => true,
                        'is_verified'  => false,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
                $created++;
                continue;
            }

            $existing = trim((string) ($primary->email_work ?? ''));

            if ($existing !== '') {
                if (strcasecmp($existing, $email) === 0) {
                    $unchanged++;
                    continue;
                }
                if (!$overwrite) {
                    $this->line("  [skip]   emp #{$au->employee_id}: email_work sudah terisi ('{$existing}') — pakai --overwrite untuk menimpa");
                    $skipped++;
                    continue;
                }
            }

            // email_work kosong, atau --overwrite dengan nilai berbeda.
            $isFill = $existing === '';
            if ($dryRun) {
                $tag = $isFill ? '[isi]   ' : '[timpa] ';
                $this->line("  {$tag} emp #{$au->employee_id}: '{$existing}' -> '{$email}'");
            } else {
                DB::table('employee_address')
                    ->where('address_id', $primary->address_id)
                    ->update(['email_work' => $email, 'updated_at' => now()]);
            }
            $isFill ? $filled++ : $updated++;
        }

        $this->newLine();
        $this->info('Ringkasan:');
        $this->line("  email_work kosong diisi   : {$filled}");
        $this->line("  alamat primary baru dibuat : {$created}");
        $this->line("  email_work ditimpa         : {$updated}");
        $this->line("  dilewati (sudah terisi)    : {$skipped}");
        $this->line("  tidak berubah (sudah sama) : {$unchanged}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY-RUN selesai — jalankan tanpa --dry-run untuk menyimpan.');
        } else {
            $this->newLine();
            $this->info('Backfill selesai.');
        }

        return 0;
    }
}

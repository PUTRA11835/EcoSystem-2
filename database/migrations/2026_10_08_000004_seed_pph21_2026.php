<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data awal PPh 21 tahun 2026 dari database/data/pph21_2026.php.
 *
 * IDEMPOTEN & ADITIF: hanya mengisi jika tahun 2026 belum punya baris di tabel terkait (tidak menimpa
 * koreksi yang sudah dibuat di Settings). down() hanya menghapus baris tahun 2026 milik seeder ini bila
 * belum diubah — demi keselamatan produksi, down() tidak menyentuh tahun lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        $d   = require database_path('data/pph21_2026.php');
        $y   = $d['year'];
        $now = now();

        if (!DB::table('pph21_ptkp')->where('year', $y)->exists()) {
            DB::table('pph21_ptkp')->insert(array_map(fn ($r) => [
                'year' => $y, 'code' => $r[0], 'description' => $r[1], 'annual_amount' => $r[2],
                'created_at' => $now, 'updated_at' => $now,
            ], $d['ptkp']));
        }

        if (!DB::table('pph21_progressive_brackets')->where('year', $y)->exists()) {
            DB::table('pph21_progressive_brackets')->insert(array_map(fn ($r, $i) => [
                'year' => $y, 'seq' => $i + 1, 'upper_limit' => $r[0], 'rate' => $r[1],
                'created_at' => $now, 'updated_at' => $now,
            ], $d['progressive'], array_keys($d['progressive'])));
        }

        if (!DB::table('pph21_ter_rates')->where('year', $y)->exists()) {
            $rows = [];
            foreach ($d['ter'] as $cat => $layers) {
                $over = 0;
                foreach ($layers as $i => [$upto, $rate]) {
                    $rows[] = [
                        'year' => $y, 'category' => $cat, 'seq' => $i + 1,
                        'gross_over' => $over, 'gross_upto' => $upto, 'rate' => $rate,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                    $over = $upto;
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('pph21_ter_rates')->insert($chunk);
            }
        }

        if (!DB::table('pph21_settings')->where('year', $y)->exists()) {
            DB::table('pph21_settings')->insert([
                'year' => $y,
                'occupational_cost_rate' => $d['settings']['occupational_cost_rate'],
                'occupational_cost_monthly_max' => $d['settings']['occupational_cost_monthly_max'],
                'include_employer_premiums' => $d['settings']['include_employer_premiums'],
                'notes' => 'Initial data from PP 58/2023, PMK 168/2023 and UU HPP. To be verified by Accounting before payroll goes live.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $y = (require database_path('data/pph21_2026.php'))['year'];
        foreach (['pph21_ter_rates', 'pph21_progressive_brackets', 'pph21_ptkp', 'pph21_settings'] as $t) {
            DB::table($t)->where('year', $y)->whereNull('updated_by')->delete();
        }
    }
};

<?php

namespace App\Services\Payroll;

use App\Support\Payroll\Pph21RateRules;
use App\Support\Payroll\PtkpRules;
use Illuminate\Support\Facades\DB;

/**
 * Penulisan pengaturan PPh 21 (halaman Settings). Setiap simpan divalidasi aturan murni lebih dulu dan
 * dibungkus transaksi; tahun yang dikunci payroll (kelak) tidak boleh diubah — lihat isYearLocked().
 */
class Pph21SettingsService
{
    public function __construct(private readonly Pph21RateRepository $rates)
    {
    }

    /**
     * Tahun dipakai payroll yang sudah disetujui? Sebelum modul Payroll ada selalu false; Fase 3 mengisinya.
     */
    public function isYearLocked(int $year): bool
    {
        return false;
    }

    /** @return string[] galat; kosong = tersimpan */
    public function savePtkp(int $year, array $amounts, ?int $actorId): array
    {
        if ($lock = $this->lockError($year)) {
            return [$lock];
        }
        if ($errors = Pph21RateRules::validatePtkp($amounts)) {
            return $errors;
        }

        DB::transaction(function () use ($year, $amounts, $actorId) {
            foreach (PtkpRules::CODES as $code) {
                DB::table('pph21_ptkp')->where('year', $year)->where('code', $code)->update([
                    'annual_amount' => round((float) $amounts[$code], 2), 'updated_by' => $actorId, 'updated_at' => now(),
                ]);
            }
        });

        return [];
    }

    /** @param array<int,array{upper_limit:mixed,rate:mixed}> $rows @return string[] */
    public function saveBrackets(int $year, array $rows, ?int $actorId): array
    {
        if ($lock = $this->lockError($year)) {
            return [$lock];
        }
        $rows = array_values($rows);
        if ($errors = Pph21RateRules::validateBrackets($rows)) {
            return $errors;
        }

        DB::transaction(function () use ($year, $rows, $actorId) {
            DB::table('pph21_progressive_brackets')->where('year', $year)->delete();
            $now = now();
            DB::table('pph21_progressive_brackets')->insert(array_map(fn ($r, $i) => [
                'year' => $year, 'seq' => $i + 1,
                'upper_limit' => ($r['upper_limit'] === null || $r['upper_limit'] === '') ? null : round((float) $r['upper_limit'], 2),
                'rate' => round((float) $r['rate'], 2),
                'updated_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
            ], $rows, array_keys($rows)));
        });

        return [];
    }

    /** @param array<int,array{upper_limit:mixed,rate:mixed}> $rows @return string[] */
    public function saveTer(int $year, string $category, array $rows, ?int $actorId): array
    {
        if ($lock = $this->lockError($year)) {
            return [$lock];
        }
        if (!in_array($category, ['A', 'B', 'C'], true)) {
            return ['Unknown TER category.'];
        }
        $rows = array_values($rows);
        if ($errors = Pph21RateRules::validateTerCategory($rows, $category)) {
            return $errors;
        }

        DB::transaction(function () use ($year, $category, $rows, $actorId) {
            DB::table('pph21_ter_rates')->where('year', $year)->where('category', $category)->delete();
            $now  = now();
            $over = 0.0;
            $out  = [];
            foreach ($rows as $i => $r) {
                $upto = ($r['upper_limit'] === null || $r['upper_limit'] === '') ? null : round((float) $r['upper_limit'], 2);
                $out[] = [
                    'year' => $year, 'category' => $category, 'seq' => $i + 1,
                    'gross_over' => $over, 'gross_upto' => $upto, 'rate' => round((float) $r['rate'], 2),
                    'updated_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
                ];
                $over = $upto ?? $over;
            }
            DB::table('pph21_ter_rates')->insert($out);
        });

        return [];
    }

    /** @return string[] */
    public function saveSettings(int $year, array $in, ?int $actorId): array
    {
        if ($lock = $this->lockError($year)) {
            return [$lock];
        }
        if ($errors = Pph21RateRules::validateSettings($in)) {
            return $errors;
        }

        DB::table('pph21_settings')->where('year', $year)->update([
            'occupational_cost_rate'        => round((float) $in['occupational_cost_rate'], 2),
            'occupational_cost_monthly_max' => round((float) $in['occupational_cost_monthly_max'], 2),
            'include_employer_premiums'     => !empty($in['include_employer_premiums']),
            'notes'                         => isset($in['notes']) ? mb_substr(trim((string) $in['notes']), 0, 1000) : null,
            'updated_by'                    => $actorId,
            'updated_at'                    => now(),
        ]);

        return [];
    }

    /**
     * Tahun baru = salinan tahun sumber (PTKP, lapisan, TER, pengaturan), lalu dikoreksi di Settings.
     *
     * @return string[]
     */
    public function createYearFrom(int $newYear, int $sourceYear, ?int $actorId): array
    {
        if ($newYear < 2024 || $newYear > 2100) {
            return ['Enter a valid tax year (2024 or later).'];
        }
        if ($this->rates->hasYear($newYear)) {
            return ["Tax year {$newYear} already exists."];
        }
        if (!$this->rates->hasYear($sourceYear)) {
            return ["Tax year {$sourceYear} has no settings to copy."];
        }

        DB::transaction(function () use ($newYear, $sourceYear, $actorId) {
            $now = now();
            foreach (['pph21_ptkp', 'pph21_progressive_brackets', 'pph21_ter_rates'] as $table) {
                $rows = DB::table($table)->where('year', $sourceYear)->get()->map(function ($r) use ($newYear, $actorId, $now) {
                    $a = (array) $r;
                    unset($a['id']);
                    $a['year'] = $newYear; $a['updated_by'] = $actorId; $a['created_at'] = $now; $a['updated_at'] = $now;

                    return $a;
                })->all();
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
            $s = (array) DB::table('pph21_settings')->where('year', $sourceYear)->first();
            unset($s['id']);
            $s['year'] = $newYear; $s['updated_by'] = $actorId; $s['created_at'] = $now; $s['updated_at'] = $now;
            $s['notes'] = "Copied from tax year {$sourceYear}. Review every table against the regulation before use.";
            DB::table('pph21_settings')->insert($s);
        });

        return [];
    }

    private function lockError(int $year): ?string
    {
        if (!$this->rates->hasYear($year)) {
            return "Tax year {$year} has not been set up.";
        }

        return $this->isYearLocked($year) ? "Tax year {$year} is used by an approved payroll and can no longer be changed." : null;
    }
}

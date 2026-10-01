<?php

namespace App\Services\Onboarding;

/**
 * Penilaian kelengkapan data master satu karyawan — MURNI, tanpa database.
 *
 * Dipisah dari OnboardingProgressService (yang memuat data dari DB) supaya
 * aturan bisa diuji cepat: jawaban "lengkap atau belum" harus sama di halaman
 * daftar, halaman detail, dan banner My Profile.
 *
 * Bentuk `$facts` (dibangun service dari database):
 *   [
 *     'employee_type'  => 'Internal'|'External'|null,
 *     'basic'          => ['gender' => 'Male', 'since_date' => null, ...],
 *     'address'        => [ ['street' => '...', 'cell_phone' => '...', 'email_work' => '...'], ... ],
 *     'identification' => [ ['identification_type' => 'KTP', 'identification_number' => '...'], ... ],
 *     'bank'           => [ ['bank_name' => '...', 'account_number' => '...', 'account_holder' => '...'], ... ],
 *     'contract'       => [ ['is_active' => 1, 'start_date' => '2026-01-01'], ... ],
 *   ]
 */
class OnboardingRules
{
    /** Jenis karyawan bila kolom employee_type kosong — perlakukan sebagai Internal. */
    public const DEFAULT_TYPE = 'Internal';

    /**
     * Nilai dianggap terisi bila bukan null dan bukan teks kosong/penanda kosong.
     * Data lama memakai "-" dan "N/A" sebagai pengganti NULL (sinkronisasi/05).
     */
    public static function filled($value): bool
    {
        if ($value === null) {
            return false;
        }

        $text = trim((string) $value);

        return $text !== '' && !in_array(mb_strtolower($text), ['-', '--', 'n/a', 'na', 'null'], true);
    }

    /** Apakah butir ini berlaku bagi jenis karyawan tersebut. */
    public static function applies(array $item, ?string $employeeType): bool
    {
        $type = self::filled($employeeType) ? $employeeType : self::DEFAULT_TYPE;

        return in_array($type, $item['applies'] ?? ['Internal', 'External'], true);
    }

    /** Satu butir lengkap atau belum, berdasarkan sumbernya. */
    public static function isDone(array $item, array $facts): bool
    {
        $column = $item['column'] ?? null;

        switch ($item['source'] ?? '') {
            case 'basic':
                return self::filled(($facts['basic'] ?? [])[$column] ?? null);

            case 'address_any':
                foreach ($facts['address'] ?? [] as $row) {
                    if (self::filled($row[$column] ?? null)) {
                        return true;
                    }
                }
                return false;

            case 'identification':
                $types = $item['types'] ?? [];
                foreach ($facts['identification'] ?? [] as $row) {
                    if (in_array($row['identification_type'] ?? null, $types, true)
                        && self::filled($row['identification_number'] ?? null)) {
                        return true;
                    }
                }
                return false;

            case 'bank':
                foreach ($facts['bank'] ?? [] as $row) {
                    if (self::filled($row[$column] ?? null)) {
                        return true;
                    }
                }
                return false;

            case 'contract_active':
                foreach ($facts['contract'] ?? [] as $row) {
                    if (!empty($row['is_active']) && self::filled($row['start_date'] ?? null)) {
                        return true;
                    }
                }
                return false;
        }

        return false;
    }

    /**
     * Nilai satu karyawan terhadap seluruh butir.
     *
     * @param  array<int,array>  $items   config('hc_onboarding.items')
     * @param  array<string,string>  $groups  config('hc_onboarding.groups')
     * @return array{
     *   items: array<int,array>, groups: array<string,array>, done: int, total: int,
     *   percent: int, status: string, missing: string[]
     * }
     */
    public static function evaluate(array $items, array $groups, array $facts): array
    {
        $type   = $facts['employee_type'] ?? null;
        $rows   = [];
        $byGroup = [];
        foreach ($groups as $key => $label) {
            $byGroup[$key] = ['label' => $label, 'done' => 0, 'total' => 0, 'missing' => []];
        }

        $done = 0;
        $total = 0;
        $missing = [];

        foreach ($items as $item) {
            if (!self::applies($item, $type)) {
                continue;
            }

            $isDone = self::isDone($item, $facts);
            $rows[] = [
                'key'     => $item['key'],
                'group'   => $item['group'],
                'label'   => $item['label'],
                'section' => $item['section'] ?? null,
                'hint'    => $item['hint'] ?? null,
                'done'    => $isDone,
            ];

            $total++;
            $byGroup[$item['group']]['total']++;
            if ($isDone) {
                $done++;
                $byGroup[$item['group']]['done']++;
            } else {
                $missing[] = $item['label'];
                $byGroup[$item['group']]['missing'][] = $item['label'];
            }
        }

        $percent = $total > 0 ? (int) round(100 * $done / $total) : 100;

        return [
            'items'   => $rows,
            'groups'  => $byGroup,
            'done'    => $done,
            'total'   => $total,
            'percent' => $percent,
            // "Tuntas" hanya bila SELURUH butir yang berlaku lengkap — bukan karena
            // pembulatan persen (99,6% dibulatkan menjadi 100).
            'status'  => ($total === 0 || $done === $total) ? 'complete' : 'in_progress',
            'missing' => $missing,
        ];
    }
}

<?php

namespace App\Support\Contracts;

/**
 * Mengubah butir kesiapan data (config/hc_contract.php `readiness`) menjadi daftar masalah yang bisa DIKLIK: tiap butir memuat nama
 * tab di halaman karyawan/konsultan, tautan langsung ke tab itu (`?section=<tab>` — dibaca master/employee/show.blade.php), dan,
 * bila ada, kolom yang disorot (`&field=` — kunci config/hc_onboarding.php).
 */
final class DataIssues
{
    /** Kunci `section` pada config → id tab (hanya yang berbeda) dan nama tab yang tampil di halaman. */
    private const TAB_LABEL = [
        'identification' => 'Identification',
        'address'        => 'Address',
        'bank'           => 'Bank Account',
        'attachment'     => 'Attachment',
        'family'         => 'Family',
        'basic_data'     => 'Basic Data',
    ];

    /** Kunci butir kesiapan yang punya kolom bernama di layar (kunci config/hc_onboarding.php). */
    private const FIELD = [
        'nik' => 'nik', 'npwp' => 'npwp', 'cell_phone' => 'cell_phone',
        'bank_name' => 'bank_name', 'bank_account' => 'bank_account', 'bank_holder' => 'bank_holder',
    ];

    /**
     * @param  array<int,array<string,mixed>>  $missing  butir `missing` dari ContractRules::readiness()
     * @param  string  $profileUrl  alamat halaman karyawan/konsultan (tanpa query)
     * @return array<int,array{key:string,label:string,tab:string,tab_label:string,url:?string,field:?string}>
     */
    public static function fromMissing(array $missing, string $profileUrl): array
    {
        return array_map(fn (array $m) => self::issue($m['key'], $m['label'], $m['section'] ?? 'basic_data', $profileUrl), $missing);
    }

    /**
     * Isian yang membuat TEKS kontrak External kosong ("alamat tidak muncul"): butir ini tidak menghalangi, hanya peringatan.
     *
     * @param  array<string,mixed>  $values  keluaran ContractService::values() (kunci placeholder → nilai)
     * @return array<int,array{key:string,label:string,tab:string,tab_label:string,url:?string,field:?string}>
     */
    public static function documentGaps(array $values, string $profileUrl): array
    {
        $map = [
            'alamat'               => ['Domicile address', 'address', 'The contract text shows the address as blank'],
            'nomor_identitas'      => ['ID card (KTP) number', 'identification', 'The contract text shows the ID number as blank'],
            'npwp'                 => ['Tax ID (NPWP) number', 'identification', 'The contract text shows the NPWP as blank'],
            'tempat_tanggal_lahir' => ['Birth place and date', 'basic-data', 'The contract text shows the birth place and date as blank'],
        ];
        $out = [];
        foreach ($map as $key => [$label, $tab, $why]) {
            if (array_key_exists($key, $values) && trim((string) (is_array($values[$key]) ? ($values[$key]['html'] ?? '') : $values[$key])) === '') {
                $out[] = self::issue($key, $label, $tab, $profileUrl) + ['why' => $why];
            }
        }

        return $out;
    }

    private static function issue(string $key, string $label, string $section, string $profileUrl): array
    {
        $tab = str_replace('_', '-', $section);
        $field = self::FIELD[$key] ?? null;

        return [
            'key'       => $key,
            'label'     => $label,
            'tab'       => $tab,
            'tab_label' => self::TAB_LABEL[str_replace('-', '_', $section)] ?? ucfirst(str_replace('-', ' ', $section)),
            'url'       => $profileUrl . '?section=' . $tab . ($field ? '&field=' . $field : ''),
            'field'     => $field,
        ];
    }
}

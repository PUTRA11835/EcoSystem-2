<?php

namespace App\Support\Validation;

/**
 * Format nomor yang berlaku di Indonesia, dengan pesan yang menyebut BERAPA digit yang benar dan berapa yang diisi.
 * Dipakai server (EmployeeIdentificationController, EmployeeBankController) dan dicerminkan di formulir (petunjuk + pemeriksaan sebelum kirim).
 * Spasi, titik dan tanda hubung diabaikan (nomor NPWP lazim ditulis 12.345.678.9-012.345); nilai yang disimpan hanya angka.
 *
 *   KTP (NIK)              16 digit
 *   NPWP                   15 digit (format lama) atau 16 digit (berbasis NIK)
 *   BPJS Kesehatan         13 digit (nomor kartu)
 *   BPJS Ketenagakerjaan   11 digit (nomor KPJ)
 *   Rekening bank          6–20 digit
 * Jenis lain (Passport, SIM, KITAS, KITAP, Other) bebas — hanya dibatasi panjang kolom.
 */
class FieldFormats
{
    public const IDENTIFICATION = [
        'KTP'                  => ['label' => 'ID Card (KTP / NIK) number',        'lengths' => [16], 'hint' => 'NIK: exactly 16 digits, as printed on the KTP.'],
        'NPWP'                 => ['label' => 'Tax ID (NPWP) number',              'lengths' => [15, 16], 'hint' => 'NPWP: 15 digits (old format) or 16 digits (NIK-based). Dots and hyphens are ignored.'],
        'BPJS_KESEHATAN'       => ['label' => 'BPJS Kesehatan number',             'lengths' => [13], 'hint' => 'Exactly 13 digits, as printed on the BPJS Kesehatan card.'],
        'BPJS_KETENAGAKERJAAN' => ['label' => 'BPJS Ketenagakerjaan (KPJ) number', 'lengths' => [11], 'hint' => 'KPJ: exactly 11 digits, as printed on the BPJS Ketenagakerjaan card.'],
    ];

    public const BANK_MIN = 6;
    public const BANK_MAX = 20;

    public static function digits(string $value): string
    {
        return preg_replace('/[\s.\-]/', '', $value) ?? '';
    }

    /** Pesan galat untuk nomor identitas, atau null bila sah / jenisnya bebas. */
    public static function identificationError(string $type, string $number): ?string
    {
        $spec = self::IDENTIFICATION[strtoupper($type)] ?? null;
        if (!$spec) {
            return null;
        }
        $clean = self::digits(trim($number));
        if ($clean === '' || !ctype_digit($clean)) {
            return "{$spec['label']} must contain digits only (spaces, dots and hyphens are ignored). " . $spec['hint'];
        }
        $len = strlen($clean);
        if (!in_array($len, $spec['lengths'], true)) {
            $need = count($spec['lengths']) === 1 ? 'exactly ' . $spec['lengths'][0] : implode(' or ', $spec['lengths']);

            return "{$spec['label']} must be {$need} digits — you entered {$len}. " . $spec['hint'];
        }

        return null;
    }

    /** Nilai yang disimpan: hanya angka untuk jenis berformat; jenis lain apa adanya. */
    public static function identificationNormalize(string $type, string $number): string
    {
        return isset(self::IDENTIFICATION[strtoupper($type)]) ? self::digits(trim($number)) : trim($number);
    }

    public static function bankAccountError(string $number): ?string
    {
        $clean = self::digits(trim($number));
        if ($clean === '' || !ctype_digit($clean)) {
            return 'Account Number must contain digits only (spaces and hyphens are ignored).';
        }
        $len = strlen($clean);
        if ($len < self::BANK_MIN || $len > self::BANK_MAX) {
            return 'Account Number must be ' . self::BANK_MIN . '–' . self::BANK_MAX . " digits — you entered {$len}.";
        }

        return null;
    }

    public static function bankAccountNormalize(string $number): string
    {
        return self::digits(trim($number));
    }
}
